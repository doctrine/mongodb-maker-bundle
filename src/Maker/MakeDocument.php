<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\Maker;

use Doctrine\Bundle\MongoDBBundle\DependencyInjection\Compiler\DoctrineMongoDBMappingsPass;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\DocumentClassGenerator;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\MongoDBHelper;
use Doctrine\ODM\MongoDB\Types\Type;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputAwareMakerInterface;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Maker\Common\UidTrait;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassDetails;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassProperty;
use Symfony\Bundle\MakerBundle\Util\ClassSourceManipulator;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

use function array_key_exists;
use function array_keys;
use function array_map;
use function class_exists;
use function dirname;
use function file_get_contents;
use function implode;
use function in_array;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function substr;

final class MakeDocument extends AbstractMaker implements InputAwareMakerInterface
{
    use UidTrait;

    private Generator $generator;
    private DocumentClassGenerator $documentClassGenerator;

    public function __construct(
        private FileManager $fileManager,
        private MongoDBHelper $mongoDBHelper,
        Generator|null $generator = null,
        DocumentClassGenerator|null $documentClassGenerator = null,
    ) {
        if ($generator === null) {
            $this->generator = new Generator($fileManager, 'App\\');
        } else {
            $this->generator = $generator;
        }

        if ($documentClassGenerator === null) {
            $this->documentClassGenerator = new DocumentClassGenerator($this->generator, $this->mongoDBHelper);
        } else {
            $this->documentClassGenerator = $documentClassGenerator;
        }
    }

    public static function getCommandName(): string
    {
        return 'make:document';
    }

    public static function getCommandDescription(): string
    {
        return 'Create or update a MongoDB ODM document class';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::OPTIONAL, sprintf('Class name of the document to create or update (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())))
            ->addOption('regenerate', null, InputOption::VALUE_NONE, 'Instead of adding new fields, simply generate the methods (e.g. getter/setter) for existing fields')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite any existing getter/setter methods')
            ->setHelp(file_get_contents(dirname(__DIR__, 2) . '/config/help/MakeDocument.txt'));

        $this->addWithUuidOption($command);

        $inputConfig->setArgumentAsNonInteractive('name');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        $documentClassName = $input->getArgument('name');
        if ($documentClassName && empty($this->verifyDocumentName($documentClassName))) {
            return;
        }

        if ($input->getOption('regenerate')) {
            $io->block([
                'This command will generate any missing methods (e.g. getters & setters) for a class or all classes in a namespace.',
                'To overwrite any existing methods, re-run this command with the --overwrite flag',
            ], null, 'fg=yellow');
            $classOrNamespace = $io->ask('Enter a class or namespace to regenerate', $this->getDocumentNamespace(), Validator::notBlank(...));

            $input->setArgument('name', $classOrNamespace);

            return;
        }

        $this->checkIsUsingUid($input);

        $argument            = $command->getDefinition()->getArgument('name');
        $question            = $this->createDocumentClassQuestion($argument->getDescription());
        $documentClassName ??= $io->askQuestion($question);

        while ($dangerous = $this->verifyDocumentName($documentClassName)) {
            if ($io->confirm(sprintf('"%s" contains one or more non-ASCII characters, which are potentially problematic with some database. It is recommended to use only ASCII characters for document names. Continue anyway?', $documentClassName), false)) {
                break;
            }

            $documentClassName = $io->askQuestion($question);
        }

        $input->setArgument('name', $documentClassName);
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $overwrite = $input->getOption('overwrite');

        // the regenerate option has entirely custom behavior
        if ($input->getOption('regenerate')) {
            $io->comment('Document regeneration is not yet implemented.');
            $this->writeSuccessMessage($io);

            return;
        }

        $documentClassDetails = $generator->createClassNameDetails(
            $input->getArgument('name'),
            'Document\\',
        );

        $classExists = class_exists($documentClassDetails->getFullName());
        if (! $classExists) {
            $documentPath = $this->documentClassGenerator->generateDocumentClass(
                documentClassDetails: $documentClassDetails,
                idType: $this->getIdType(),
            );

            $generator->writeChanges();
        }

        if ($classExists) {
            $documentPath = $this->getPathOfClass($documentClassDetails->getFullName());
            $io->text('Your document already exists! So let\'s add some new fields!');
        } else {
            $io->text([
                '',
                'Document generated! Now let\'s add some fields!',
                'You can always add more fields later manually or by re-running this command.',
            ]);
        }

        $currentFields = $this->getPropertyNames($documentClassDetails->getFullName());
        $manipulator   = $this->createClassManipulator($documentPath, $io, $overwrite);

        $isFirstField = true;
        while (true) {
            $newField     = $this->askForNextField($io, $currentFields, $documentClassDetails->getFullName(), $isFirstField);
            $isFirstField = false;

            if ($newField === null) {
                break;
            }

            if ($newField instanceof ClassProperty) {
                $manipulator->addEntityField($newField);
                $currentFields[] = $newField->propertyName;
            }

            $this->fileManager->dumpFile($documentPath, $manipulator->getSourceCode());
        }

        $this->writeSuccessMessage($io);
        $io->text([
            'Next: Add more fields with the same command, or start using your document!',
            '',
        ]);
    }

    public function configureDependencies(DependencyBuilder $dependencies, InputInterface|null $input = null): void
    {
        $dependencies->addClassDependency(
            DoctrineMongoDBMappingsPass::class,
            'mongodb-odm-bundle',
        );
    }

    /** @param string[] $fields */
    private function askForNextField(ConsoleStyle $io, array $fields, string $documentClass, bool $isFirstField): ClassProperty|null
    {
        $io->writeln('');

        if ($isFirstField) {
            $questionText = 'New property name (press <return> to stop adding fields)';
        } else {
            $questionText = 'Add another property? Enter the property name (or press <return> to stop adding fields)';
        }

        $fieldName = $io->ask($questionText, null, function ($name) use ($fields) {
            // allow it to be empty
            if (! $name) {
                return $name;
            }

            if (in_array($name, $fields, true)) {
                throw new InvalidArgumentException(sprintf('The "%s" property already exists.', $name));
            }

            return Validator::validateDoctrineFieldName($name, $this->mongoDBHelper->getRegistry());
        });

        if (! $fieldName) {
            return null;
        }

        $defaultType = 'string';
        // try to guess the type by the field name prefix/suffix
        $snakeCasedField = Str::asSnakeCase($fieldName);

        $suffix = substr($snakeCasedField, -3);
        if ($suffix === '_at') {
            $defaultType = 'date_immutable';
        } elseif ($suffix === '_id') {
            $defaultType = 'int';
        } elseif (str_starts_with($snakeCasedField, 'is_')) {
            $defaultType = 'bool';
        } elseif (str_starts_with($snakeCasedField, 'has_')) {
            $defaultType = 'bool';
        }

        $type  = null;
        $types = $this->getTypesMap();

        $allValidTypes = array_keys($types);

        while ($type === null) {
            $question = new Question('Field type (enter <comment>?</comment> to see all types)', $defaultType);
            $question->setAutocompleterValues($allValidTypes);
            $type = $io->askQuestion($question);

            if ($type === '?') {
                $this->printAvailableTypes($io);
                $io->writeln('');

                $type = null;
            } elseif (! in_array($type, $allValidTypes, true)) {
                $this->printAvailableTypes($io);
                $io->error(sprintf('Invalid type "%s".', $type));
                $io->writeln('');

                $type = null;
            }
        }

        // this is a normal field
        $classProperty = new ClassProperty(propertyName: $fieldName, type: $type);

        if ($type === 'string') {
            // MongoDB doesn't have length constraints at the database level
            // but we can still track it for validation purposes
            $classProperty->length = null;
        }

        if ($io->confirm('Can this field be null in the database (nullable)', false)) {
            $classProperty->nullable = true;
        }

        return $classProperty;
    }

    private function printAvailableTypes(ConsoleStyle $io): void
    {
        $allTypes = $this->getTypesMap();

        $typesTable = [
            'main' => [
                'string' => [],
                'int' => [],
                'float' => [],
                'bool' => [],
            ],
            'array_object' => [
                'hash' => [],
                'collection' => [],
                'object_id' => [],
            ],
            'date_time' => [
                'date' => ['date_immutable'],
                'timestamp' => [],
            ],
        ];

        $printSection = static function (array $sectionTypes) use ($io, &$allTypes): void {
            foreach ($sectionTypes as $mainType => $subTypes) {
                if (! array_key_exists($mainType, $allTypes)) {
                    continue;
                }

                foreach ($subTypes as $key => $potentialType) {
                    if (! array_key_exists($potentialType, $allTypes)) {
                        unset($subTypes[$key]);
                    }

                    unset($allTypes[$potentialType]);
                }

                unset($allTypes[$mainType]);

                $line = sprintf('  * <comment>%s</comment>', $mainType);

                if ($subTypes !== []) {
                    $line .= sprintf(' or %s', implode(' or ', array_map(
                        static fn ($subType) => sprintf('<comment>%s</comment>', $subType),
                        $subTypes,
                    )));
                }

                $io->writeln($line);
            }

            $io->writeln('');
        };

        $io->writeln('<info>Main Types</info>');
        $printSection($typesTable['main']);

        $io->writeln('<info>Array/Object Types</info>');
        $printSection($typesTable['array_object']);

        $io->writeln('<info>Date/Time Types</info>');
        $printSection($typesTable['date_time']);

        $io->writeln('<info>Other Types</info>');
        $allTypes = array_map(static fn () => [], $allTypes);
        $printSection($allTypes);
    }

    private function createDocumentClassQuestion(string $questionText): Question
    {
        $question = new Question($questionText);
        $question->setValidator(Validator::notBlank(...));
        $question->setAutocompleterValues($this->mongoDBHelper->getDocumentsForAutocomplete());

        return $question;
    }

    /** @return string[] */
    private function verifyDocumentName(string $documentName): array
    {
        preg_match('/([^\x00-\x7F]+)/u', $documentName, $matches);

        return $matches;
    }

    private function createClassManipulator(string $path, ConsoleStyle $io, bool $overwrite): ClassSourceManipulator
    {
        $manipulator = new ClassSourceManipulator(
            sourceCode: $this->fileManager->getFileContents($path),
            overwrite: $overwrite,
        );

        $manipulator->setIo($io);

        return $manipulator;
    }

    private function getPathOfClass(string $class): string
    {
        return (new ClassDetails($class))->getPath();
    }

    /** @return string[] */
    private function getPropertyNames(string $class): array
    {
        if (! class_exists($class)) {
            return [];
        }

        $reflClass = new ReflectionClass($class);

        return array_map(static fn (ReflectionProperty $prop) => $prop->getName(), $reflClass->getProperties());
    }

    private function getDocumentNamespace(): string
    {
        return $this->mongoDBHelper->getDocumentNamespace();
    }

    /** @return array<string, string> */
    private function getTypesMap(): array
    {
        return Type::getTypesMap();
    }
}
