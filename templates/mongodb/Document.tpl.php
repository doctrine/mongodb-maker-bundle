<?php

use Symfony\Bundle\MakerBundle\Maker\Common\EntityIdTypeEnum;

?>
<?= "<?php\n" ?>

namespace <?= $namespace ?>;

<?= $use_statements; ?>

#[ODM\Document(collection: '<?= $collection_name ?>', repositoryClass: <?= $repository_class_name ?>::class)]
class <?= $class_name."\n" ?>
{
<?php if (EntityIdTypeEnum::UUID === $id_type): ?>
    #[ODM\Id(strategy: 'UUID')]
    public ?string $id = null;
<?php elseif (EntityIdTypeEnum::ULID === $id_type): ?>
    #[ODM\Id(strategy: 'UUID')]
    public ?string $id = null;
<?php else: ?>
    #[ODM\Id]
    public ?string $id = null;
<?php endif ?>
}
