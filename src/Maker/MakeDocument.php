<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\Maker;

use Doctrine\Bundle\MongoDBBundle\DoctrineMongoDBBundle;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\ClassSourceManipulator;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\DocumentClassGenerator;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\DocumentRelation;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\MongoDBHelper;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\Validator;
use Doctrine\ODM\MongoDB\Types\Type;
use Exception;
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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function class_exists;
use function dirname;
use function file_get_contents;
use function implode;
use function in_array;
use function preg_match;
use function property_exists;
use function sprintf;
use function str_starts_with;
use function substr;

final class MakeDocument extends AbstractMaker implements InputAwareMakerInterface
{
    use UidTrait;

    private DocumentClassGenerator $documentClassGenerator;
    private Generator $generator;

    public function __construct(
        private FileManager $fileManager,
        private MongoDBHelper $mongoDBHelper,
        ?Generator $generator = null,
        ?DocumentClassGenerator $documentClassGenerator = null,
    ) {
        $this->generator              = $generator ?? new Generator($fileManager, 'App\\');
        $this->documentClassGenerator = $documentClassGenerator ?? new DocumentClassGenerator($this->generator, $this->mongoDBHelper);
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
            ->setHelp((string) file_get_contents(dirname(__DIR__, 2) . '/config/help/MakeDocument.txt'));

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
            $io->block(['This command will generate any missing methods (e.g. getters & setters) for a class or all classes in a namespace.'], null, 'fg=yellow');
            $classOrNamespace = $io->ask('Enter a class or namespace to regenerate', $this->getDocumentNamespace(), Validator::notBlank(...));

            $input->setArgument('name', $classOrNamespace);

            return;
        }

        $this->checkIsUsingUid($input);

        $argument            = $command->getDefinition()->getArgument('name');
        $question            = $this->createDocumentClassQuestion($argument->getDescription());
        $documentClassName ??= $io->askQuestion($question);

        while ($this->verifyDocumentName($documentClassName)) {
            if ($io->confirm(sprintf('"%s" contains one or more non-ASCII characters, which can be problematic with MongoDB. It is recommended to use only ASCII characters for document names. Continue anyway?', $documentClassName), false)) {
                break;
            }

            $documentClassName = $io->askQuestion($question);
        }

        $input->setArgument('name', $documentClassName);
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
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
            $io->text([
                '',
                'Document generated! Now let\'s add some fields!',
                'You can always add more fields later manually or by re-running this command.',
            ]);
        } else {
            $documentPath = $this->getPathOfClass($documentClassDetails->getFullName());
            $io->text('Your document already exists! So let\'s add some new fields!');
        }

        $currentFields = $this->getPropertyNames($documentClassDetails->getFullName());
        $manipulator   = $this->createClassManipulator($documentPath);

        $isFirstField = true;
        while (true) {
            $newField     = $this->askForNextField($io, $currentFields, $documentClassDetails->getFullName(), $isFirstField);
            $isFirstField = false;

            if ($newField === null) {
                break;
            }

            $fileManagerOperations                = [];
            $fileManagerOperations[$documentPath] = $manipulator;

            if ($newField instanceof ClassProperty) {
                $manipulator->addDocumentField($newField);
                $currentFields[] = $newField->propertyName;
            } elseif ($newField instanceof DocumentRelation) {
                // Handle relation
                $newFieldName = $newField->getOwningProperty();

                if ($newField->isSelfReferencing()) {
                    $otherManipulatorFilename = $documentPath;
                    $otherManipulator         = $manipulator;
                } else {
                    $otherManipulatorFilename = $this->getPathOfClass($newField->getInverseClass());
                    $otherManipulator         = $this->createClassManipulator($otherManipulatorFilename);
                }

                switch ($newField->getType()) {
                    case DocumentRelation::REFERENCE_ONE:
                        if ($newField->getOwningClass() === $documentClassDetails->getFullName()) {
                            $manipulator->addReferenceOne(
                                $newField->getOwningProperty(),
                                $newField->getTargetClassName(),
                                $newField->isNullable(),
                                $newField->getOwningRelationOptions(),
                            );

                            if ($newField->getMapInverseRelation()) {
                                $otherManipulator->addReferenceMany(
                                    $newField->getInverseProperty(),
                                    $newField->getOwningClass(),
                                    $newField->getInverseRelationOptions(),
                                );
                            }
                        }

                        break;

                    case DocumentRelation::REFERENCE_MANY:
                        $manipulator->addReferenceMany(
                            $newField->getOwningProperty(),
                            $newField->getTargetClassName(),
                            $newField->getOwningRelationOptions(),
                        );

                        if ($newField->getMapInverseRelation()) {
                            $otherManipulator->addReferenceOne(
                                $newField->getInverseProperty(),
                                $newField->getOwningClass(),
                                false,
                                $newField->getInverseRelationOptions(),
                            );
                        }

                        break;

                    case DocumentRelation::EMBED_ONE:
                        $manipulator->addEmbedOne(
                            $newField->getOwningProperty(),
                            $newField->getTargetClassName(),
                            $newField->isNullable(),
                        );
                        break;

                    case DocumentRelation::EMBED_MANY:
                        $manipulator->addEmbedMany(
                            $newField->getOwningProperty(),
                            $newField->getTargetClassName(),
                        );
                        break;

                    default:
                        throw new InvalidArgumentException('Invalid relation type');
                }

                // save the inverse side if it's being mapped
                if ($newField->getMapInverseRelation()) {
                    $fileManagerOperations[$otherManipulatorFilename] = $otherManipulator;
                }

                $currentFields[] = $newFieldName;
            } else {
                throw new Exception('Invalid field type');
            }

            foreach ($fileManagerOperations as $path => $manipulatorOrMessage) {
                $this->fileManager->dumpFile($path, $manipulatorOrMessage->getSourceCode());
            }
        }

        $this->writeSuccessMessage($io);
        $io->text([
            'Next: Add more fields with the same command, or start using your document!',
            '',
        ]);
    }

    public function configureDependencies(DependencyBuilder $dependencies, ?InputInterface $input = null): void
    {
        $dependencies->addClassDependency(
            DoctrineMongoDBBundle::class,
            'doctrine/mongodb-odm-bundle',
        );
    }

    /** @param string[] $fields */
    private function askForNextField(ConsoleStyle $io, array $fields, string $documentClass, bool $isFirstField): DocumentRelation|ClassProperty|null
    {
        $io->writeln('');

        if ($isFirstField) {
            $questionText = 'New property name (press <return> to stop adding fields)';
        } else {
            $questionText = 'Add another property? Enter the property name (or press <return> to stop adding fields)';
        }

        $fieldName = $io->ask($questionText, null, static function ($name) use ($fields) {
            // allow it to be empty
            if (! $name) {
                return $name;
            }

            if (in_array($name, $fields, true)) {
                throw new InvalidArgumentException(sprintf('The "%s" property already exists.', $name));
            }

            return Validator::validateFieldName($name);
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

        $allValidTypes = array_merge(
            array_keys($types),
            DocumentRelation::getValidRelationTypes(),
            ['relation'],
        );

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

        if ($type === 'relation' || in_array($type, DocumentRelation::getValidRelationTypes(), true)) {
            return $this->askRelationDetails($io, $documentClass, $type, $fieldName);
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

        $io->writeln('<info>Relations</info>');
        $io->writeln(' * <comment>ReferenceOne</comment>');
        $io->writeln(' * <comment>ReferenceMany</comment>');
        $io->writeln(' * <comment>EmbedOne</comment>');
        $io->writeln(' * <comment>EmbedMany</comment>');
        $io->writeln(' * <comment>relation</comment> (alias, will prompt you to pick the type)');
    }

    private function askRelationDetails(ConsoleStyle $io, string $generatedDocumentClass, string $type, string $newFieldName): DocumentRelation
    {
        // ask the targetDocument
        $targetDocumentClass = null;
        while ($targetDocumentClass === null) {
            $question = $this->createDocumentClassQuestion('What class should this document be related to?');

            $answeredDocumentClass = $io->askQuestion($question);

            // find the correct class name
            if (class_exists($this->getDocumentNamespace() . '\\' . $answeredDocumentClass)) {
                $targetDocumentClass = $this->getDocumentNamespace() . '\\' . $answeredDocumentClass;
            } elseif (class_exists($answeredDocumentClass)) {
                $targetDocumentClass = $answeredDocumentClass;
            } else {
                $io->error(sprintf('Unknown class "%s"', $answeredDocumentClass));
            }
        }

        // help the user select the type
        if ($type === 'relation') {
            $type = $this->askRelationType($io, $generatedDocumentClass, $targetDocumentClass);
        }

        $askFieldName = static fn (string $targetClass, string $defaultValue) => $io->ask(
            sprintf('New field name inside %s', Str::getShortClassName($targetClass)),
            $defaultValue,
            static function ($name) use ($targetClass) {
                if (property_exists($targetClass, $name)) {
                    throw new InvalidArgumentException(sprintf('The "%s" class already has a "%s" property.', $targetClass, $name));
                }

                return Validator::validateFieldName($name);
            },
        );

        $askIsNullable = static fn (string $propertyName, string $targetClass) => $io->confirm(sprintf(
            'Is the <comment>%s</comment>.<comment>%s</comment> property allowed to be null (nullable)?',
            Str::getShortClassName($targetClass),
            $propertyName,
        ));

        $askOrphanRemoval = static function (string $owningClass, string $inverseClass) use ($io) {
            $io->text([
                'Do you want to activate <comment>orphanRemoval</comment> on your relationship?',
                sprintf(
                    'A <comment>%s</comment> is "orphaned" when it is removed from its related <comment>%s</comment>.',
                    Str::getShortClassName($owningClass),
                    Str::getShortClassName($inverseClass),
                ),
                '',
                sprintf(
                    'NOTE: If a <comment>%s</comment> may *change* from one <comment>%s</comment> to another, answer "no".',
                    Str::getShortClassName($owningClass),
                    Str::getShortClassName($inverseClass),
                ),
            ]);

            return $io->confirm(sprintf('Do you want to automatically delete orphaned <comment>%s</comment> objects (orphanRemoval)?', $owningClass), false);
        };

        $askInverseSide = static function (DocumentRelation $relation) use ($io): void {
            $recommendMappingInverse = $relation->getType() !== DocumentRelation::REFERENCE_ONE;

            $mapInverse = $io->confirm(
                sprintf(
                    'Do you want to add a new property to <comment>%s</comment> so that you can access/update <comment>%s</comment> objects from it?',
                    Str::getShortClassName($relation->getInverseClass()),
                    Str::getShortClassName($relation->getOwningClass()),
                ),
                $recommendMappingInverse,
            );
            $relation->setMapInverseRelation($mapInverse);
        };

        switch ($type) {
            case DocumentRelation::REFERENCE_ONE:
                $relation = new DocumentRelation(
                    DocumentRelation::REFERENCE_ONE,
                    $generatedDocumentClass,
                    $targetDocumentClass,
                );
                $relation->setOwningProperty($newFieldName);

                $relation->setIsNullable($askIsNullable(
                    $relation->getOwningProperty(),
                    $relation->getOwningClass(),
                ));

                $askInverseSide($relation);
                if ($relation->getMapInverseRelation()) {
                    $io->comment(sprintf(
                        'A new property will also be added to the <comment>%s</comment> class so that you can access the related <comment>%s</comment> objects from it.',
                        Str::getShortClassName($relation->getInverseClass()),
                        Str::getShortClassName($relation->getOwningClass()),
                    ));
                    $relation->setInverseProperty($askFieldName(
                        $relation->getInverseClass(),
                        Str::singularCamelCaseToPluralCamelCase(Str::getShortClassName($relation->getOwningClass())),
                    ));

                    if (! $relation->isNullable()) {
                        $relation->setOrphanRemoval($askOrphanRemoval(
                            $relation->getOwningClass(),
                            $relation->getInverseClass(),
                        ));
                    }
                }

                break;

            case DocumentRelation::REFERENCE_MANY:
                $relation = new DocumentRelation(
                    DocumentRelation::REFERENCE_MANY,
                    $generatedDocumentClass,
                    $targetDocumentClass,
                );
                $relation->setOwningProperty($newFieldName);

                $askInverseSide($relation);
                if ($relation->getMapInverseRelation()) {
                    $io->comment(sprintf(
                        'A new property will also be added to the <comment>%s</comment> class.',
                        Str::getShortClassName($relation->getInverseClass()),
                    ));
                    $relation->setInverseProperty($askFieldName(
                        $relation->getInverseClass(),
                        Str::asLowerCamelCase(Str::getShortClassName($relation->getOwningClass())),
                    ));

                    $relation->setOrphanRemoval($askOrphanRemoval(
                        $relation->getInverseClass(),
                        $relation->getOwningClass(),
                    ));
                }

                break;

            case DocumentRelation::EMBED_ONE:
                $relation = new DocumentRelation(
                    DocumentRelation::EMBED_ONE,
                    $generatedDocumentClass,
                    $targetDocumentClass,
                );
                $relation->setOwningProperty($newFieldName);

                $relation->setIsNullable($askIsNullable(
                    $relation->getOwningProperty(),
                    $relation->getOwningClass(),
                ));

                break;

            case DocumentRelation::EMBED_MANY:
                $relation = new DocumentRelation(
                    DocumentRelation::EMBED_MANY,
                    $generatedDocumentClass,
                    $targetDocumentClass,
                );
                $relation->setOwningProperty($newFieldName);

                break;

            default:
                throw new InvalidArgumentException(sprintf('Invalid relation type "%s".', $type));
        }

        return $relation;
    }

    private function askRelationType(ConsoleStyle $io, string $documentClass, string $targetDocumentClass): string
    {
        $io->writeln('What type of relationship is this?');

        $originalDocumentShort = Str::getShortClassName($documentClass);
        $targetDocumentShort   = Str::getShortClassName($targetDocumentClass);

        $rows   = [];
        $rows[] = [
            DocumentRelation::REFERENCE_ONE,
            sprintf("Each <comment>%s</comment> relates to (has) <info>one</info> <comment>%s</comment>.\nEach <comment>%s</comment> can relate to (can have) <info>many</info> <comment>%s</comment> objects.", $originalDocumentShort, $targetDocumentShort, $targetDocumentShort, $originalDocumentShort),
        ];
        $rows[] = ['', ''];
        $rows[] = [
            DocumentRelation::REFERENCE_MANY,
            sprintf("Each <comment>%s</comment> can relate to (can have) <info>many</info> <comment>%s</comment> objects.\nEach <comment>%s</comment> relates to (has) <info>one</info> <comment>%s</comment>.", $originalDocumentShort, $targetDocumentShort, $targetDocumentShort, $originalDocumentShort),
        ];
        $rows[] = ['', ''];
        $rows[] = [
            DocumentRelation::EMBED_ONE,
            sprintf('Each <comment>%s</comment> embeds <info>one</info> <comment>%s</comment> (stored in same document).', $originalDocumentShort, $targetDocumentShort),
        ];
        $rows[] = ['', ''];
        $rows[] = [
            DocumentRelation::EMBED_MANY,
            sprintf('Each <comment>%s</comment> embeds <info>many</info> <comment>%s</comment> objects (stored in same document).', $originalDocumentShort, $targetDocumentShort),
        ];

        $io->table(['Type', 'Description'], $rows);

        $question = new Question(sprintf(
            'Relation type? [%s]',
            implode(', ', DocumentRelation::getValidRelationTypes()),
        ));
        $question->setAutocompleterValues(DocumentRelation::getValidRelationTypes());
        $question->setValidator(static function ($type) {
            if (! in_array($type, DocumentRelation::getValidRelationTypes(), true)) {
                throw new InvalidArgumentException(sprintf('Invalid type: %s.', $type));
            }

            return $type;
        });

        return $io->askQuestion($question);
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

    private function createClassManipulator(string $path): ClassSourceManipulator
    {
        return new ClassSourceManipulator(
            sourceCode: $this->fileManager->getFileContents($path),
        );
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
