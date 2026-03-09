<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\Maker;

use Doctrine\Bundle\MongoDBBundle\DoctrineMongoDBBundle;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\ClassSourceManipulator;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\MongoDBHelper;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\Validator;
use Doctrine\ODM\MongoDB\Mapping\Attribute\Id;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\MakerInterface;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassDetails;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

use function array_filter;
use function array_key_first;
use function array_map;
use function array_values;
use function class_exists;
use function count;
use function dirname;
use function file_get_contents;
use function in_array;
use function preg_match;
use function sprintf;
use function strtolower;

final class MakeIndex extends AbstractMaker implements MakerInterface
{
    private const string ARG_DOCUMENT = 'document';

    public function __construct(
        private readonly FileManager $fileManager,
        private readonly MongoDBHelper $mongoDBHelper,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:document:index';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument(self::ARG_DOCUMENT, InputArgument::OPTIONAL, sprintf('Class name of the document to create the index for (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())))
            ->setHelp((string) file_get_contents(dirname(__DIR__, 2) . '/config/help/MakeIndex.txt'));

        $inputConfig->setArgumentAsNonInteractive(self::ARG_DOCUMENT);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(
            DoctrineMongoDBBundle::class,
            'doctrine/mongodb-odm-bundle',
        );
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        $documentClassName = $input->getArgument(self::ARG_DOCUMENT);
        if ($documentClassName && empty($this->verifyDocumentName($documentClassName))) {
            return;
        }

        $argument            = $command->getDefinition()->getArgument(self::ARG_DOCUMENT);
        $question            = $this->createDocumentClassQuestion($argument->getDescription());
        $documentClassName ??= $io->askQuestion($question);

        while ($this->verifyDocumentName($documentClassName)) {
            if ($io->confirm(sprintf('"%s" contains one or more non-ASCII characters, which can be problematic with MongoDB. It is recommended to use only ASCII characters for document names. Continue anyway?', $documentClassName), false)) {
                break;
            }

            $documentClassName = $io->askQuestion($question);
        }

        $input->setArgument(self::ARG_DOCUMENT, $documentClassName);
    }

    private function createDocumentClassQuestion(string $questionText): Question
    {
        $question = new Question($questionText);
        $question->setValidator(Validator::notBlank(...));
        $question->setAutocompleterValues($this->mongoDBHelper->getDocumentsForAutocomplete());

        return $question;
    }

    /** @throws Exception */
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $documentName = $input->getArgument(self::ARG_DOCUMENT);

        $documentClassDetails = $generator->createClassNameDetails(
            $documentName,
            'Document\\',
        );

        if (! class_exists($documentClassDetails->getFullName())) {
            throw new Exception(sprintf(
                'Document "%s" does not exist. You can generate it by running %s %s',
                $documentName,
                MakeDocument::getCommandName(),
                $documentName,
            ));
        }

        $documentClass   = $documentClassDetails->getFullName();
        $documentPath    = new ClassDetails($documentClass)->getPath();
        $reflectionClass = new ReflectionClass($documentClass);

        /** Filter any identifiers. We don't consider them valid candidates for `MakeIndex` */
        $fields = array_values(array_filter(array_map(static function (ReflectionProperty $prop) {
            $isId = ! empty($prop->getAttributes(Id::class));

            return $isId ? null : $prop->getName();
        }, $reflectionClass->getProperties())));

        if (empty($fields)) {
            throw new Exception(sprintf(
                'Document "%s" does not have any fields that can be indexed. Please add some fields before running this command.',
                $documentName,
            ));
        }

        $manipulator = new ClassSourceManipulator(
            sourceCode: $this->fileManager->getFileContents($documentPath),
        );

        $this->addIndexRecursive($io, $manipulator, $fields, $documentPath);
    }

    /** @return string[] */
    private function verifyDocumentName(string $documentName): array
    {
        preg_match('/([^\x00-\x7F]+)/u', $documentName, $matches);

        return $matches;
    }

    /** @param string[] $fields */
    private function addIndexRecursive(ConsoleStyle $io, ClassSourceManipulator $manipulator, array $fields, string $documentPath): void
    {
        $io->writeln('');

        $unique = $io->askQuestion(new ConfirmationQuestion('Should this index be unique?', false));
        $keys   = $this->askForKeys($io, $fields);

        $this->createAttribute($unique, $keys, $manipulator);
        $this->fileManager->dumpFile($documentPath, $manipulator->getSourceCode());

        $io->info('Index added.');

        $createAnother = $io->ask('Would you like to add another index to this document? (y/n)', 'n', static function ($answer) {
            if (! in_array(strtolower($answer), ['y', 'n'], true)) {
                throw new InvalidArgumentException('Please enter "y" or "n".');
            }

            return strtolower($answer) === 'y';
        });

        if (! $createAnother) {
            $this->writeSuccessMessage($io);

            $io->writeln(
                'Remember to run "php bin/console doctrine:mongodb:schema:update" to create your indexes in the database.',
            );

            return;
        }

        $this->addIndexRecursive($io, $manipulator, $fields, $documentPath);
    }

    /**
     * @param array<string,string> $keys

     * @throws Exception
     */
    private function createAttribute(bool $unique, array $keys, ClassSourceManipulator $manipulator): void
    {
        $attributeClass       = $unique ? 'ODM\UniqueIndex' : 'ODM\Index';
        $createClassAttribute = count($keys) > 1;

        if ($createClassAttribute) {
            $manipulator->addAttributeToClass($attributeClass, ['keys' => $keys]);

            return;
        }

        $field = array_key_first($keys);
        $manipulator->addAttributeToProperty($attributeClass, (string) $field, ['order' => $keys[$field]]);
    }

    /**
     * @param string[] $fields
     *
     * @return array<string,string> $keys
     */
    private function askForKeys(ConsoleStyle $io, array $fields): array
    {
        $question = new ChoiceQuestion('Select one or more keys for the index (or <return> to finish)', $fields);
        $question->setMultiselect(true);

        $selection = $io->askQuestion($question);

        $keys = [];

        foreach ($selection as $key) {
            $orderQuestion = new ChoiceQuestion(
                sprintf('Provide the order for key "%s"', $key),
                ['asc', 'desc'],
                'asc',
            );

            $keys[$key] = $io->askQuestion($orderQuestion);
        }

        return $keys;
    }
}
