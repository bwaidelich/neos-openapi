<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

final readonly class Post
{
    private function __construct(
        public PostSlug $slug,
        public PostTitle $title,
    ) {}

    public static function create(PostSlug $slug, PostTitle $title): self
    {
        return new self($slug, $title);
    }
}
