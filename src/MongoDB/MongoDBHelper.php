<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\MakerBundle\Str;

use function array_first;
use function array_keys;
use function array_pop;
use function count;
use function explode;
use function implode;
use function str_contains;

/** @internal */
final class MongoDBHelper
{
    private const string DEFAULT_DOCUMENT_NAMESPACE = 'App\\Document';

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

    public function getDocumentNamespace(): string
    {
        $documentManager = $this->registry->getManager();

        if (! $documentManager instanceof DocumentManager) {
            return self::DEFAULT_DOCUMENT_NAMESPACE;
        }

        $metadataDriver     = $documentManager->getConfiguration()->getMetadataDriverImpl();
        $documentNamespaces = [];

        if ($metadataDriver === null) {
            return self::DEFAULT_DOCUMENT_NAMESPACE;
        }

        foreach ($metadataDriver->getAllClassNames() as $className) {
            $parts = explode('\\', $className);
            if (count($parts) <= 1) {
                continue;
            }

            array_pop($parts);
            $documentNamespaces[implode('\\', $parts)] = true;
        }

        $namespaces = array_keys($documentNamespaces);

        // Prefer 'Document' namespace if it exists
        foreach ($namespaces as $namespace) {
            if (str_contains($namespace, '\\Document')) {
                return $namespace;
            }
        }

        return array_first($namespaces) ?? self::DEFAULT_DOCUMENT_NAMESPACE;
    }

    /** @return string[] */
    public function getDocumentsForAutocomplete(): array
    {
        $documentManager = $this->registry->getManager();

        if (! $documentManager instanceof DocumentManager) {
            return [];
        }

        $metadataDriver = $documentManager->getConfiguration()->getMetadataDriverImpl();
        $allDocuments   = [];

        if ($metadataDriver === null) {
            return [];
        }

        foreach ($metadataDriver->getAllClassNames() as $className) {
            $allDocuments[] = $className;
            $allDocuments[] = Str::getShortClassName($className);
        }

        return $allDocuments;
    }
}
