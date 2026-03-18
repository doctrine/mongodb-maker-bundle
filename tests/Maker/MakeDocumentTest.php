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
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\MakerBundle\Test\MakerTestCase;
use Symfony\Bundle\MakerBundle\Test\MakerTestDetails;
use Symfony\Bundle\MakerBundle\Test\MakerTestRunner;
use Symfony\Component\HttpKernel\KernelInterface;

use function getenv;

#[Group('functional')]
class MakeDocumentTest extends MakerTestCase
{
    protected function getMakerClass(): string
    {
        return MakeDocument::class;
    }

    /**
     * Redeclare the method to work around PHPStorm issue
     *
     * @see https://youtrack.jetbrains.com/projects/WI/issues/WI-69800/Run-single-data-set-cant-test-individual-dataset-if-test-method-with-dataprovider-is-inherited-wrong-cli-command-generated
     */
    #[DataProvider('getTestDetails')]
    #[Override]
    public function testExecute(MakerTestDetails $makerTestDetails): void
    {
        parent::testExecute($makerTestDetails);
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

        yield 'it_creates_document_with_reference_one_relation' => [
            self::createMakeDocumentTest()
            ->run(static function (MakerTestRunner $runner): void {
                // First, create the Category document
                $runner->runMaker([
                    // document class name
                    'Category',
                    // add name field
                    'name',
                    'string',
                    'n',
                    // no more fields
                    '',
                ]);

                // Now create Article with ReferenceOne to Category
                $runner->runMaker([
                    // document class name
                    'Article',
                    // add title field
                    'title',
                    'string',
                    'n',
                    // add category reference
                    'category',         // field name
                    'ReferenceOne',     // relation type
                    'Category',         // target document
                    'y',                // nullable
                    'n',                // map inverse
                    '',
                ]);

                // TODO: Add relation assertions
            }),
        ];

        yield 'it_creates_document_with_reference_many_relation' => [
            self::createMakeDocumentTest()
            ->run(static function (MakerTestRunner $runner): void {
                // First, create the Comment document
                $runner->runMaker([
                    'Comment',
                    'text',
                    'string',
                    'n',
                    '',
                ]);

                // Now create Article with ReferenceMany to Category
                $runner->runMaker([
                    // document class name
                    'Article',
                    // add title field
                    'title',
                    'string',
                    'n',
                    // add category references
                    'comments',         // field name
                    'ReferenceMany',    // relation type
                    'Comment',          // target document
                    'y',                // map inverse
                    'article',          // mapped by
                    'n',                // orphan removal
                    '',
                ]);

                // TODO: Add relation assertions
            }),
        ];

        yield 'it_creates_document_with_embed_one_relation' => [
            self::createMakeDocumentTest()
            ->run(static function (MakerTestRunner $runner): void {
                // Create an embedded document (PhoneNumber)
                // Note: For now, we create it as a regular document
                // Later the command should support --embedded flag
                $runner->runMaker([
                    'PhoneNumber',
                    'number',
                    'string',
                    'type',
                    'string',
                    '',
                ]);

                // Create User with EmbedMany to PhoneNumber
                $runner->runMaker([
                    'User',
                    'name',
                    'string',
                    // add phone number embeds
                    'phoneNumber',      // field name
                    'EmbedOne',         // relation type
                    'PhoneNumber',      // target document
                    'y',                // nullable
                    '',
                ]);

                // TODO: Add relation assertions
            }),
        ];

        yield 'it_creates_document_with_embed_many_relation' => [
            self::createMakeDocumentTest()
            ->run(static function (MakerTestRunner $runner): void {
                // Create an embedded document (PhoneNumber)
                // Note: For now, we create it as a regular document
                // Later the command should support --embedded flag
                $runner->runMaker([
                    'PhoneNumber',
                    'number',
                    'string',
                    'type',
                    'string',
                    '',
                ]);

                // Create User with EmbedMany to PhoneNumber
                $runner->runMaker([
                    'User',
                    'name',
                    'string',
                    // add phone number embeds
                    'phoneNumbers',     // field name
                    'EmbedMany',        // relation type
                    'PhoneNumber',      // target document
                    '',
                ]);

                // TODO: Add relation assertions
            }),
        ];

        yield 'it_creates_a_self_referencing_document' => [
            self::createMakeDocumentTest()
            ->run(static function (MakerTestRunner $runner): void {
                $runner->runMaker([
                    // document class name
                    'User',
                    // add name field
                    'name',
                    'string',
                    'n',
                    // add friends reference (self-referencing ReferenceMany)
                    'friends',
                    'ReferenceMany',
                    'User',
                    'y', // map inverse
                    'friendOf', // mapped by (arbitrary, for test)
                    'n', // orphan removal
                    '',
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
