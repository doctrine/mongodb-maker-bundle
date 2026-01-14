<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document]
class User
{
    #[ODM\Id]
    public ?int $id = null;

    #[ODM\Field]
    public ?string $firstName = null;
}
