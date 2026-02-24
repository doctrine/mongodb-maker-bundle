<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

/** @internal */
final class RelationEmbedMany extends BaseRelation
{
    /** @param array<string, mixed> $mapping */
    public static function createFromMapping(array $mapping): self
    {
        return new self(
            propertyName: $mapping['fieldName'] ?? $mapping['name'],
            targetClassName: $mapping['targetDocument'],
        );
    }
}
