<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

use Exception;
use InvalidArgumentException;

use function in_array;
use function sprintf;

/** @internal */
final class DocumentRelation
{
    public const string REFERENCE_ONE = 'ReferenceOne';
    public const string REFERENCE_MANY = 'ReferenceMany';
    public const string EMBED_ONE = 'EmbedOne';
    public const string EMBED_MANY = 'EmbedMany';

    private string $owningProperty;
    private string $inverseProperty;
    private bool $isNullable = false;
    private bool $isSelfReferencing = false;
    private bool $orphanRemoval = false;
    private bool $mapInverseRelation = true;

    public function __construct(
        private string $type,
        private string $owningClass,
        private string $inverseClass,
    ) {
        if (! in_array($type, self::getValidRelationTypes())) {
            throw new Exception(sprintf('Invalid relation type "%s"', $type));
        }

        $this->isSelfReferencing = $owningClass === $inverseClass;
    }

    public function setOwningProperty(string $owningProperty): void
    {
        $this->owningProperty = $owningProperty;
    }

    public function setInverseProperty(string $inverseProperty): void
    {
        if (! $this->mapInverseRelation) {
            throw new Exception('Cannot call setInverseProperty() when the inverse relation will not be mapped.');
        }

        $this->inverseProperty = $inverseProperty;
    }

    public function setIsNullable(bool $isNullable): void
    {
        $this->isNullable = $isNullable;
    }

    public function setOrphanRemoval(bool $orphanRemoval): void
    {
        $this->orphanRemoval = $orphanRemoval;
    }

    /** @return string[] */
    public static function getValidRelationTypes(): array
    {
        return [
            self::REFERENCE_ONE,
            self::REFERENCE_MANY,
            self::EMBED_ONE,
            self::EMBED_MANY,
        ];
    }

    public function getOwningRelation(): RelationReferenceOne|RelationEmbedOne
    {
        return match ($this->getType()) {
            self::REFERENCE_ONE => new RelationReferenceOne(
                propertyName: $this->owningProperty,
                targetClassName: $this->inverseClass,
                targetPropertyName: $this->inverseProperty,
                isSelfReferencing: $this->isSelfReferencing,
                mapInverseRelation: $this->mapInverseRelation,
                isOwning: true,
                isNullable: $this->isNullable,
            ),
            self::EMBED_ONE => new RelationEmbedOne(
                propertyName: $this->owningProperty,
                targetClassName: $this->inverseClass,
                isNullable: $this->isNullable,
            ),
            self::REFERENCE_MANY, self::EMBED_MANY => throw new InvalidArgumentException('Use getInverseRelation for many relations'),
            default => throw new InvalidArgumentException('Invalid type'),
        };
    }

    public function getInverseRelation(): RelationReferenceMany|RelationEmbedMany
    {
        return match ($this->getType()) {
            self::REFERENCE_MANY => new RelationReferenceMany(
                propertyName: $this->inverseProperty,
                targetClassName: $this->owningClass,
                targetPropertyName: $this->owningProperty,
                isSelfReferencing: $this->isSelfReferencing,
                orphanRemoval: $this->orphanRemoval,
            ),
            self::EMBED_MANY => new RelationEmbedMany(
                propertyName: $this->inverseProperty,
                targetClassName: $this->owningClass,
            ),
            self::REFERENCE_ONE, self::EMBED_ONE => throw new InvalidArgumentException('Use getOwningRelation for one relations'),
            default => throw new InvalidArgumentException('Invalid type'),
        };
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getOwningClass(): string
    {
        return $this->owningClass;
    }

    public function getInverseClass(): string
    {
        return $this->inverseClass;
    }

    public function getOwningProperty(): string
    {
        return $this->owningProperty;
    }

    public function getInverseProperty(): string
    {
        return $this->inverseProperty;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function isSelfReferencing(): bool
    {
        return $this->isSelfReferencing;
    }

    public function getMapInverseRelation(): bool
    {
        return $this->mapInverseRelation;
    }

    public function setMapInverseRelation(bool $mapInverseRelation): void
    {
        if ($mapInverseRelation && isset($this->inverseProperty)) {
            throw new Exception('Cannot set setMapInverseRelation() to true when the inverse relation property is set.');
        }

        $this->mapInverseRelation = $mapInverseRelation;
    }

    public function getTargetClassName(): string
    {
        return $this->inverseClass;
    }

    /** @return array<string, mixed> */
    public function getOwningRelationOptions(): array
    {
        $options = [];

        if ($this->mapInverseRelation && isset($this->inverseProperty)) {
            $options['inversedBy'] = $this->inverseProperty;
        }

        if ($this->orphanRemoval && $this->getType() === self::REFERENCE_MANY) {
            $options['orphanRemoval'] = true;
        }

        return $options;
    }

    /** @return array<string, mixed> */
    public function getInverseRelationOptions(): array
    {
        $options = [];

        if ($this->mapInverseRelation && isset($this->owningProperty)) {
            $options['mappedBy'] = $this->owningProperty;
        }

        if ($this->orphanRemoval) {
            $options['orphanRemoval'] = true;
        }

        return $options;
    }
}
