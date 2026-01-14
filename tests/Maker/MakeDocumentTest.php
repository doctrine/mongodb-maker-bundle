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
use function sprintf;
use function strpos;
use function substr;

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
                // @todo We have to re-install the bundles to get the recipe-contrib applied
                // https://github.com/symfony/flex/issues/1076
                // https://github.com/symfony/recipes/pull/1509
                $runner->runProcess('composer remove --dev doctrine/mongodb-maker-bundle doctrine/mongodb-odm-bundle --no-scripts');
                $runner->runProcess('composer config extra.symfony.allow-contrib true');
                $runner->runProcess('composer require --dev doctrine/mongodb-maker-bundle doctrine/mongodb-odm-bundle');

                if (! $withDatabase) {
                    return;
                }

                $runner->replaceInFile(
                    '.env',
                    'mongodb://localhost:27017',
                    getenv('MONGODB_URI'),
                );

                self::runDocumentTest($runner);
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
                    // add not additional fields
                    '',
                ]);

                self::runDocumentTest($runner, ['firstName' => 'Dev']);
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

        //$runner->updateSchema();
        $runner->runTests();
    }

    private static function runCustomTest(MakerTestRunner $runner, string $filename, bool $withDatabase = true): void
    {
        $runner->copy(
            'make-document/tests/' . $filename,
            'tests/GeneratedDocumentTest.php',
        );

        if ($withDatabase) {
            $runner->updateSchema();
        }

        $runner->runTests();
    }

    private static function copyDocument(MakerTestRunner $runner, string $filename): void
    {
        $documentClassName = substr(
            $filename,
            0,
            strpos($filename, '-'),
        );

        $runner->copy(
            sprintf('make-document/documents/attributes/%s', $filename),
            sprintf('src/Document/%s.php', $documentClassName),
        );
    }

    private static function copyDocumentDirectory(MakerTestRunner $runner, string $directory): void
    {
        $runner->copy(
            sprintf('make-document/%s/attributes', $directory),
            '',
        );
    }
}
