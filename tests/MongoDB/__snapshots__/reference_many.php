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

    /** @var Collection<array-key, Comment> */
    #[ODM\ReferenceMany(targetDocument: Comment::class, orphanRemoval: true, mappedBy: 'article')]
    private(set) Collection $comments;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
    }
}
