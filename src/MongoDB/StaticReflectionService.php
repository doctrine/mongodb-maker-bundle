<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

use Doctrine\Persistence\Mapping\ReflectionService;
use ReflectionClass;
use ReflectionProperty;

use function str_contains;
use function strpos;
use function strrev;
use function strrpos;
use function substr;

/** @internal replacing removed Doctrine\Persistence\Mapping\StaticReflectionService */
final class StaticReflectionService implements ReflectionService
{
    /** @return string[] */
    public function getParentClasses(string $class): array
    {
        return [];
    }

    public function getClassShortName(string $class): string
    {
        $nsSeparatorLastPosition = strrpos($class, '\\');

        if ($nsSeparatorLastPosition !== false) {
            $class = substr($class, $nsSeparatorLastPosition + 1);
        }

        return $class;
    }

    public function getClassNamespace(string $class): string
    {
        $namespace = '';

        if (str_contains($class, '\\')) {
            $namespace = strrev(substr(strrev($class), (int) strpos(strrev($class), '\\') + 1));
        }

        return $namespace;
    }

    public function getClass(string $class): ReflectionClass
    {
        return new ReflectionClass($class);
    }

    public function getAccessibleProperty(string $class, string $property): ReflectionProperty|null
    {
        return null;
    }

    public function hasPublicMethod(string $class, string $method): bool
    {
        return true;
    }
}
