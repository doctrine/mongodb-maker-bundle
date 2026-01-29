<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;

#[ODM\Document]
class User
{
    #[ODM\Id]
    public int|null $id = null;

    #[ODM\Field]
    public string|null $firstName = null;
}
