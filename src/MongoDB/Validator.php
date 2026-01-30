<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;

use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function var_export;

final class Validator
{
    /**
     * Validates that a value is not blank.
     */
    public static function notBlank(string|null $value = null): string
    {
        if ($value === null || $value === '') {
            throw new RuntimeCommandException('This value cannot be blank.');
        }

        return $value;
    }

    /**
     * Validates a MongoDB document field name.
     *
     * MongoDB field names:
     * - Cannot be empty
     * - Cannot start with $ (reserved for operators)
     * - Cannot contain null character
     * - Cannot contain dots in most contexts (used for nested documents)
     * - Should be valid PHP property names
     */
    public static function validateFieldName(string|null $name): string
    {
        if ($name === null || $name === '') {
            throw new RuntimeCommandException('Field name cannot be empty.');
        }

        // MongoDB-specific restrictions
        if (str_starts_with($name, '$')) {
            throw new RuntimeCommandException(sprintf('Field name %s cannot start with "$" (reserved for MongoDB operators).', var_export($name, true)));
        }

        if (str_contains($name, "\0")) {
            throw new RuntimeCommandException(sprintf('Field name %s cannot contain null characters.', var_export($name, true)));
        }

        // Check for valid PHP property name (starts with letter or underscore, followed by letters, numbers, or underscores)
        if (! preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $name)) {
            throw new RuntimeCommandException(sprintf('%s is not a valid PHP property name.', var_export($name, true)));
        }

        return $name;
    }
}
