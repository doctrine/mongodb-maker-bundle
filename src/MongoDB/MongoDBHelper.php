<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

use DateTime;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Types\Type;
use Doctrine\Persistence\ManagerRegistry;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Uid\Uuid;

use function array_first;
use function array_flip;
use function array_keys;
use function array_pop;
use function count;
use function explode;
use function implode;
use function sprintf;
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

    public static function canFieldTypeBeInferredByPropertyType(string $fieldType, string $propertyType): bool
    {
        return match ($propertyType) {
            '\\' . DateTime::class => $fieldType === Type::DATE,
            '\\' . DateTimeImmutable::class => $fieldType === Type::DATE_IMMUTABLE,
            Uuid::class => $fieldType === Type::UUID,
            'array' => $fieldType === Type::HASH,
            'bool' => $fieldType === Type::BOOL,
            'float' => $fieldType === Type::FLOAT,
            'int' => $fieldType === Type::INT,
            'string' => $fieldType === Type::STRING,
            default => false,
        };
    }

    /** @throws ReflectionException */
    public static function getPropertyTypeForField(string $fieldType): string|null
    {
        $propertyType = match ($fieldType) {
            Type::STRING,
            Type::BINDATA,
            Type::BINDATABYTEARRAY,
            Type::BINDATACUSTOM,
            Type::BINDATAFUNC,
            Type::BINDATAMD5,
            Type::BINDATAUUID,
            Type::BINDATAUUIDRFC4122,
            Type::DECIMAL128,
            Type::ID,
            Type::TIMESTAMP => 'string',
            Type::INT => 'int',
            Type::FLOAT => 'float',
            Type::BOOL => 'bool',
            Type::HASH,
            Type::COLLECTION,
            Type::OBJECTID,
            Type::VECTOR_FLOAT32,
            Type::VECTOR_INT8,
            Type::VECTOR_PACKED_BIT => 'array',
            Type::DATE => '\\' . DateTime::class,
            Type::DATE_IMMUTABLE => '\\' . DateTimeImmutable::class,
            Type::UUID => '\\' . Uuid::class,
            default => null,
        };

        $typesMap = Type::getTypesMap();
        if ($propertyType !== null || ! isset($typesMap[$fieldType])) {
            return $propertyType;
        }

        $reflection = new ReflectionClass($typesMap[$fieldType]);
        $returnType = $reflection->getMethod('convertToPHPValue')->getReturnType();

        /*
         * we do not support union and intersection types
         */
        if (! $returnType instanceof ReflectionNamedType) {
            return null;
        }

        return $returnType->isBuiltin() ? $returnType->getName() : '\\' . $returnType->getName();
    }

    /**
     * Given the string "field type", this returns the "Types::STRING" constant.
     *
     * This is, effectively, a reverse lookup: given the final string, give us
     * the constant to be used in the generated code.
     */
    public static function getTypeConstant(string $fieldType): string|null
    {
        $reflection = new ReflectionClass(Type::class);
        $constants  = array_flip($reflection->getConstants());

        if (! isset($constants[$fieldType])) {
            return null;
        }

        return sprintf('Type::%s', $constants[$fieldType]);
    }
}
