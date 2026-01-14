<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\MakerBundle\Str;

use function array_keys;
use function array_pop;
use function count;
use function explode;
use function implode;
use function in_array;
use function lcfirst;
use function str_contains;

/** @internal */
final class MongoDBHelper
{
    // MongoDB reserved keywords that should be escaped
    private const array MONGODB_KEYWORDS = [
        'type',
        'class',
        'namespace',
        'abstract',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'eval',
        'exit',
        'extends',
        'final',
        'finally',
        'fn',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'match',
        'new',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'readonly',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        'yield',
    ];

    public function __construct(
        private ManagerRegistry $registry,
    ) {
    }

    public function getRegistry(): ManagerRegistry
    {
        return $this->registry;
    }

    public function getPotentialCollectionName(string $className): string
    {
        $shortClassName = Str::getShortClassName($className);

        return Str::asSnakeCase($shortClassName);
    }

    public function isKeyword(string $name): bool
    {
        return in_array(lcfirst($name), self::MONGODB_KEYWORDS, true);
    }

    public function getDocumentNamespace(): string
    {
        $documentManager = $this->registry->getManager();

        if (! $documentManager instanceof DocumentManager) {
            return 'App\\Document';
        }

        $configuration      = $documentManager->getConfiguration();
        $documentNamespaces = [];

        foreach ($configuration->getMetadataDriverImpl()->getAllClassNames() as $className) {
            $parts = explode('\\', $className);
            if (count($parts) <= 1) {
                continue;
            }

            array_pop($parts);
            $namespace                      = implode('\\', $parts);
            $documentNamespaces[$namespace] = true;
        }

        $namespaces = array_keys($documentNamespaces);

        // Prefer 'Document' namespace if it exists
        foreach ($namespaces as $namespace) {
            if (str_contains($namespace, '\\Document')) {
                return $namespace;
            }
        }

        return $namespaces[0] ?? 'App\\Document';
    }

    /** @return string[] */
    public function getDocumentsForAutocomplete(): array
    {
        $documentManager = $this->registry->getManager();

        if (! $documentManager instanceof DocumentManager) {
            return [];
        }

        $configuration = $documentManager->getConfiguration();
        $allDocuments  = [];

        foreach ($configuration->getMetadataDriverImpl()->getAllClassNames() as $className) {
            $allDocuments[] = $className;
            $allDocuments[] = Str::getShortClassName($className);
        }

        return $allDocuments;
    }
}
