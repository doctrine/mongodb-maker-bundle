<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Maker\Common\EntityIdTypeEnum;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Bundle\MakerBundle\Util\UseStatementGenerator;

use function dirname;

/** @internal */
final class DocumentClassGenerator
{
    public function __construct(
        private Generator $generator,
        private MongoDBHelper $mongoDBHelper,
    ) {
    }

    public function generateDocumentClass(
        ClassNameDetails $documentClassDetails,
        bool $generateRepositoryClass = true,
        EntityIdTypeEnum $idType = EntityIdTypeEnum::INT,
    ): string {
        $repoClassDetails = $this->generator->createClassNameDetails(
            $documentClassDetails->getRelativeName(),
            'Repository\\',
            'Repository',
        );

        $collectionName = $this->mongoDBHelper->getPotentialCollectionName($documentClassDetails->getFullName());

        $useStatements = new UseStatementGenerator([
            $repoClassDetails->getFullName(),
            ['Doctrine\\ODM\\MongoDB\\Mapping\\Attribute' => 'ODM'],
        ]);

        $templatePath = dirname(__DIR__, 2) . '/templates/mongodb/Document.tpl.php';
        $documentPath = $this->generator->generateClass(
            $documentClassDetails->getFullName(),
            $templatePath,
            [
                'use_statements' => $useStatements,
                'repository_class_name' => $repoClassDetails->getShortName(),
                'collection_name' => $collectionName,
                'id_type' => $idType,
            ],
        );

        if ($generateRepositoryClass) {
            $this->generateRepositoryClass(
                $repoClassDetails->getFullName(),
                $documentClassDetails->getFullName(),
                true,
            );
        }

        return $documentPath;
    }

    public function generateRepositoryClass(
        string $repositoryClass,
        string $documentClass,
        bool $includeExampleComments = true,
    ): void {
        $shortDocumentClass = Str::getShortClassName($documentClass);

        $useStatements = new UseStatementGenerator([
            $documentClass,
            DocumentManager::class,
            DocumentRepository::class,
        ]);

        $templatePath = dirname(__DIR__, 2) . '/templates/mongodb/Repository.tpl.php';
        $this->generator->generateClass(
            $repositoryClass,
            $templatePath,
            [
                'use_statements' => $useStatements,
                'document_class_name' => $shortDocumentClass,
                'include_example_comments' => $includeExampleComments,
            ],
        );
    }
}
