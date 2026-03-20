<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB\Exception;

use Exception;

use function sprintf;

final class IncompatibleParentRelationException extends Exception
{
    /** @param string[] $parents */
    private function __construct(string $message, public readonly array $parents)
    {
        parent::__construct($message);
    }

    /** @internal */
    public static function forEmbeddingParents(string $className, string ...$parents): self
    {
        return new self(
            sprintf('Document %s can\'t be made standalone as it\'s embedded by the following class(es):', $className),
            $parents,
        );
    }

    /** @internal */
    public static function forReferencingParents(string $className, string ...$parents): self
    {
        return new self(
            sprintf('Can\'t mark %s as embedded as it\'s referenced by the following class(es):', $className),
            $parents,
        );
    }
}
