<?php

declare(strict_types=1);

/*
 * This file is part of the Doctrine MongoDB Maker Bundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\MongoDBMakerBundle\Tests\MongoDB;

use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\MongoDBHelper;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class MongoDBHelperTest extends TestCase
{
    private MongoDBHelper $helper;
    private ManagerRegistry&Stub $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createStub(ManagerRegistry::class);
        $this->helper   = new MongoDBHelper($this->registry);
    }

    #[DataProvider('getPotentialCollectionNameDataProvider')]
    public function testGetPotentialCollectionName(string $className, string $expectedCollectionName): void
    {
        $result = $this->helper->getPotentialCollectionName($className);

        $this->assertSame($expectedCollectionName, $result);
    }

    /** @return Generator<string, array{0: string, 1: string}> */
    public static function getPotentialCollectionNameDataProvider(): Generator
    {
        yield 'simple class name' => [
            'App\\Document\\User',
            'user',
        ];

        yield 'camel case to snake case' => [
            'App\\Document\\UserProfile',
            'user_profile',
        ];

        yield 'already snake case' => [
            'App\\Document\\user_account',
            'user_account',
        ];
    }

    public function testGetDocumentNamespaceWhenDocumentManagerIsNotAvailable(): void
    {
        $this->registry->method('getManager')->willReturn($this->createStub(ObjectManager::class));
        $result = $this->helper->getDocumentNamespace();

        $this->assertSame('App\\Document', $result);
    }

    /** @param class-string[] $classNames */
    #[DataProvider('getDocumentNamespaceWithDocumentsDataProvider')]
    public function testGetDocumentNamespaceWithDocuments(array $classNames, string $expectedNamespace): void
    {
        $documentManagerMock = $this->createStub(DocumentManager::class);
        $configMock          = $this->createStub(Configuration::class);
        $driverMock          = $this->createStub(AttributeDriver::class);

        $driverMock->method('getAllClassNames')->willReturn($classNames);
        $configMock->method('getMetadataDriverImpl')->willReturn($driverMock);
        $documentManagerMock->method('getConfiguration')->willReturn($configMock);

        $this->registry->method('getManager')->willReturn($documentManagerMock);
        $result = $this->helper->getDocumentNamespace();

        $this->assertSame($expectedNamespace, $result);
    }

    /** @return Generator<string, array<int, mixed>> */
    public static function getDocumentNamespaceWithDocumentsDataProvider(): Generator
    {
        yield 'single class with Document namespace' => [
            ['App\\Document\\User'],
            'App\\Document',
        ];

        yield 'multiple classes in Document namespace' => [
            ['App\\Document\\User', 'App\\Document\\Post'],
            'App\\Document',
        ];

        yield 'classes in Document and other namespace' => [
            ['App\\Models\\Post', 'App\\Document\\User'],
            'App\\Document',
        ];

        yield 'single class without Document namespace' => [
            ['App\\Entity\\User'],
            'App\\Entity',
        ];

        yield 'multiple classes without Document namespace' => [
            ['App\\Entity\\User', 'App\\Entity\\Post'],
            'App\\Entity',
        ];

        yield 'multiple different namespaces without Document' => [
            ['App\\Entity\\User', 'App\\Models\\Post'],
            'App\\Entity',
        ];

        yield 'multiple different namespaces with Document' => [
            ['App\\Entity\\User', 'App\\Document\\Post', 'App\\Models\\Product'],
            'App\\Document',
        ];

        yield 'nested Document namespace' => [
            ['Doctrine\\Bundle\\MongoDBMakerBundle\\Document\\BlogPost'],
            'Doctrine\\Bundle\\MongoDBMakerBundle\\Document',
        ];

        yield 'single class without namespace' => [
            ['SingleClass'],
            'App\\Document',
        ];

        yield 'empty class list' => [
            [],
            'App\\Document',
        ];
    }
}
