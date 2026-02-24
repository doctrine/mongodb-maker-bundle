<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;

#[ODM\Document]
class TestDocument
{
    #[ODM\Id]
    public string $id;

    #[ODM\Field(nullable: false)]
    public string $title;
}
