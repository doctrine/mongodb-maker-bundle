<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;

#[ODM\Document]
class Article
{
    #[ODM\Id]
    public string $id;

    #[ODM\Field(type: 'string')]
    public string $title;

    /** @var Collection<array-key, PhoneNumber> */
    #[ODM\EmbedMany(targetDocument: PhoneNumber::class)]
    private(set) Collection $phoneNumbers;

    public function __construct()
    {
        $this->phoneNumbers = new ArrayCollection();
    }
}
