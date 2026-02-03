<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

/** @internal */
abstract class BaseCollectionRelation extends BaseRelation
{
    public function __construct(
        string $propertyName,
        string $targetClassName,
        string|null $targetPropertyName = null,
        bool $isSelfReferencing = false,
        bool $mapInverseRelation = true,
        bool $orphanRemoval = false,
    ) {
        parent::__construct(
            propertyName: $propertyName,
            targetClassName: $targetClassName,
            targetPropertyName: $targetPropertyName,
            isSelfReferencing: $isSelfReferencing,
            mapInverseRelation: $mapInverseRelation,
            avoidSetter: true,
            isCustomReturnTypeNullable: false,
            customReturnType: 'Collection',
            orphanRemoval: $orphanRemoval,
        );
    }
}
