<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

/** @internal */
abstract class BaseRelation
{
    public function __construct(
        private string $propertyName,
        private string $targetClassName,
        private string|null $targetPropertyName = null,
        private bool $isSelfReferencing = false,
        private bool $mapInverseRelation = true,
        private bool $avoidSetter = false,
        private bool $isCustomReturnTypeNullable = false,
        private string|null $customReturnType = null,
        private bool $isOwning = false,
        private bool $orphanRemoval = false,
        private bool $isNullable = false,
    ) {
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    public function getTargetClassName(): string
    {
        return $this->targetClassName;
    }

    public function getTargetPropertyName(): string|null
    {
        return $this->targetPropertyName;
    }

    public function isSelfReferencing(): bool
    {
        return $this->isSelfReferencing;
    }

    public function getMapInverseRelation(): bool
    {
        return $this->mapInverseRelation;
    }

    public function shouldAvoidSetter(): bool
    {
        return $this->avoidSetter;
    }

    public function getCustomReturnType(): string|null
    {
        return $this->customReturnType;
    }

    public function isCustomReturnTypeNullable(): bool
    {
        return $this->isCustomReturnTypeNullable;
    }

    public function isOwning(): bool
    {
        return $this->isOwning;
    }

    public function getOrphanRemoval(): bool
    {
        return $this->orphanRemoval;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }
}
