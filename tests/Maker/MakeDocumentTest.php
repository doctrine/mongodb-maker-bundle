<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\MongoDBMakerBundle\Tests\Maker;

use Doctrine\Bundle\MongoDBMakerBundle\Maker\MakeDocument;
use Generator;
use Symfony\Bundle\MakerBundle\Test\MakerTestCase;
use Symfony\Bundle\MakerBundle\Test\MakerTestDetails;
use Symfony\Bundle\MakerBundle\Test\MakerTestRunner;
use Symfony\Component\HttpKernel\KernelInterface;

use function getenv;

class MakeDocumentTest extends MakerTestCase
{
    protected function getMakerClass(): string
    {
        return MakeDocument::class;
    }

    private static function createMakeDocumentTest(bool $withDatabase = true): MakerTestDetails
    {
        return self::buildMakerTest()
            ->preRun(static function (MakerTestRunner $runner) use ($withDatabase): void {
                if (! $withDatabase) {
                    return;
                }

                $runner->replaceInFile(
                    '.env',
                    'mongodb://localhost:27017',
                    (string) getenv('MONGODB_URI'),
                );
            });
    }

    protected function createKernel(): KernelInterface
    {
        return new MakerTestKernel('dev', true);
    }

    public static function getTestDetails(): Generator
    {
        yield 'it_creates_a_new_class_basic' => [
            self::createMakeDocumentTest()
            ->run(static function (MakerTestRunner $runner): void {
                $runner->runMaker([
                    // document class name
                    'User',
                    // no additional fields
                    '',
                ]);

                self::runDocumentTest($runner);
            }),
        ];

        yield 'it_creates_a_new_class_with_fields' => [
            self::createMakeDocumentTest()
            ->run(static function (MakerTestRunner $runner): void {
                $runner->runMaker([
                    // document class name
                    'User',
                    // additional fields
                    'some_id',
                    'uuid',
                    'y',
                    'firstName',
                    'string',
                    'n',
                    'isActive',
                    'bool',
                    'n',
                    'friends',
                    'collection',
                    'n',
                    '',
                ]);

                self::runDocumentTest($runner, [
                    'firstName' => 'Pauline',
                    'isActive' => true,
                    'friends' => ['Alice', 'Bob'],
                ]);
            }),
        ];
    }

    /** @param array<string, mixed> $data */
    private static function runDocumentTest(MakerTestRunner $runner, array $data = []): void
    {
        $runner->renderTemplateFile(
            'make-document/GeneratedDocumentTest.php.twig',
            'tests/GeneratedDocumentTest.php',
            ['data' => $data],
        );

        $runner->runTests();
    }
}
