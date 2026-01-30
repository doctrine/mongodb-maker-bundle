<?php

use Symfony\Bundle\MakerBundle\Maker\Common\EntityIdTypeEnum;

?>
<?= "<?php\n" ?>

namespace <?= $namespace ?>;

<?= $use_statements; ?>

#[ODM\Document(repositoryClass: <?= $repository_class_name ?>::class)]
#[ODM\Collection(name: '<?= $collection_name ?>')]
class <?= $class_name."\n" ?>
{
<?php if (EntityIdTypeEnum::UUID === $id_type): ?>
    #[ODM\Id(strategy: 'UUID')]
    private ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }
<?php elseif (EntityIdTypeEnum::ULID === $id_type): ?>
    #[ODM\Id(strategy: 'UUID')]
    private ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }
<?php else: ?>
    #[ODM\Id]
    private ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }
<?php endif ?>
}

