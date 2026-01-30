<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\Tests\MongoDB;

use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\Validator;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;

class ValidatorTest extends TestCase
{
    #[DataProvider('validNotBlankProvider')]
    public function testNotBlankWithValidValue(string $value): void
    {
        $result = Validator::notBlank($value);

        $this->assertSame($value, $result);
    }

    /** @return Generator<string, array{0: string}> */
    public static function validNotBlankProvider(): Generator
    {
        yield 'simple string' => ['valid'];
        yield 'string with spaces' => ['hello world'];
        yield 'single character' => ['a'];
        yield 'whitespace only' => [' '];
    }

    #[DataProvider('invalidNotBlankProvider')]
    public function testNotBlankWithInvalidValue(string|null $value): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('This value cannot be blank.');

        Validator::notBlank($value);
    }

    /** @return Generator<string, array{0: string|null}> */
    public static function invalidNotBlankProvider(): Generator
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
    }

    #[DataProvider('validFieldNameProvider')]
    public function testValidateFieldNameWithValidNames(string $fieldName, string $expected): void
    {
        $result = Validator::validateFieldName($fieldName);

        $this->assertSame($expected, $result);
    }

    /** @return Generator<string, array{0: string, 1: string}> */
    public static function validFieldNameProvider(): Generator
    {
        yield 'simple name' => ['name', 'name'];
        yield 'camelCase' => ['firstName', 'firstName'];
        yield 'with underscore prefix' => ['_private', '_private'];
        yield 'with numbers' => ['field1', 'field1'];
        yield 'snake_case' => ['user_name', 'user_name'];
        yield 'uppercase' => ['CONSTANT', 'CONSTANT'];
        yield 'mixed case with numbers' => ['myField2Test', 'myField2Test'];
        yield 'dollar prefix is trimmed' => ['$field', 'field'];
        yield 'multiple dollar prefix is trimmed' => ['$$field', 'field'];
    }

    #[DataProvider('emptyFieldNameProvider')]
    public function testValidateFieldNameWithEmptyValue(string $fieldName): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('Field name cannot be empty.');

        Validator::validateFieldName($fieldName);
    }

    /** @return Generator<string, array{0: string|null}> */
    public static function emptyFieldNameProvider(): Generator
    {
        yield 'empty string' => [''];
        yield 'dollar only' => ['$'];
        yield 'multiple dollars only' => ['$$$'];
    }

    #[DataProvider('invalidPhpPropertyNameProvider')]
    public function testValidateFieldNameWithInvalidPhpPropertyNames(string $fieldName): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessageMatches('/is not a valid PHP property name/');

        Validator::validateFieldName($fieldName);
    }

    /** @return Generator<string, array{0: string}> */
    public static function invalidPhpPropertyNameProvider(): Generator
    {
        yield 'starts with number' => ['1field'];
        yield 'contains dash' => ['field-name'];
        yield 'contains space' => ['field name'];
        yield 'contains special char' => ['field@name'];
    }

    #[DataProvider('mongoDbRestrictedFieldNameProvider')]
    public function testValidateFieldNameWithMongoDbRestrictions(string $fieldName, string $expectedMessagePattern): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessageMatches($expectedMessagePattern);

        Validator::validateFieldName($fieldName);
    }

    /** @return Generator<string, array{0: string, 1: string}> */
    public static function mongoDbRestrictedFieldNameProvider(): Generator
    {
        yield 'contains dot' => ['field.name', '/cannot contain a dot/'];
        yield 'null character' => ["field\0name", '/cannot contain null characters/'];
    }
}
