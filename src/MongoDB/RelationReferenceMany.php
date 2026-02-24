<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

/** @internal */
final class RelationReferenceMany extends BaseRelation
{
    /** @param array<string, mixed> $mapping */
    public static function createFromMapping(array $mapping): self
    {
        return new self(
            propertyName: $mapping['fieldName'] ?? $mapping['name'],
            targetClassName: $mapping['targetDocument'],
            targetPropertyName: $mapping['mappedBy'] ?? null,
            orphanRemoval: $mapping['orphanRemoval'] ?? false,
        );
    }
}
