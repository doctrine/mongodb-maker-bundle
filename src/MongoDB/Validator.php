<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;

use function ltrim;
use function preg_match;
use function sprintf;
use function str_contains;

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
     * - $ prefix is trimmed (reserved for operators)
     * - Cannot contain null character
     * - Cannot contain dots (used for nested documents)
     * - Should be valid PHP property names
     */
    public static function validateFieldName(string|null $name): string
    {
        if ($name === null || $name === '') {
            throw new RuntimeCommandException('Field name cannot be empty.');
        }

        // Trim $ prefix (reserved for MongoDB operators)
        $name = ltrim($name, '$');

        if ($name === '') {
            throw new RuntimeCommandException('Field name cannot be empty.');
        }

        // MongoDB-specific restrictions
        if (str_contains($name, '.')) {
            throw new RuntimeCommandException(sprintf('Field name "%s" cannot contain a dot (used for nested documents).', $name));
        }

        if (str_contains($name, "\0")) {
            throw new RuntimeCommandException(sprintf('Field name "%s" cannot contain null characters.', $name));
        }

        // Check for valid PHP property name (starts with letter or underscore, followed by letters, numbers, or underscores)
        if (! preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $name)) {
            throw new RuntimeCommandException(sprintf('"%s" is not a valid PHP property name.', $name));
        }

        return $name;
    }
}
