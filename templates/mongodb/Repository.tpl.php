<?= "<?php\n" ?>

namespace <?= $namespace ?>;

<?= $use_statements; ?>

/**
 * @extends DocumentRepository<<?= $document_class_name ?>>
 */
class <?= $class_name ?> extends DocumentRepository
{
    public function __construct(DocumentManager $dm)
    {
        parent::__construct($dm, $dm->getUnitOfWork(), $dm->getClassMetadata(<?= $document_class_name ?>::class));
    }

<?php if ($include_example_comments): ?>
    /**
     * @return <?= $document_class_name ?>[]
     */
    public function findByExampleField(mixed $value): array
    {
        return $this->createQueryBuilder()
            ->field('exampleField')->equals($value)
            ->getQuery()
            ->execute()
            ->toArray();
    }

    public function findOneBySomeField(mixed $value): ?<?= $document_class_name ?>

    {
        return $this->createQueryBuilder()
            ->field('exampleField')->equals($value)
            ->getQuery()
            ->getSingleResult();
    }
<?php endif ?>
}

