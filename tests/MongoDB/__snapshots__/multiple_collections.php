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
    #[ODM\ReferenceMany(targetDocument: Comment::class, mappedBy: 'article')]
    private(set) Collection $comments;

    /** @var Collection<array-key, Tag> */
    #[ODM\EmbedMany(targetDocument: Tag::class)]
    private(set) Collection $tags;

    /** @var Collection<array-key, Like> */
    #[ODM\ReferenceMany(targetDocument: Like::class)]
    private(set) Collection $likes;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->likes = new ArrayCollection();
    }
}
