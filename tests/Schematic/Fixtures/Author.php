<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\Schematic\Attributes\Description;

#[Description('An author of posts')]
final readonly class Author
{
    private function __construct(
        public AuthorName $name,
        public PostCount $posts,
        public AuthorName|null $pseudonym = null,
    ) {}

    public static function of(AuthorName $name): self
    {
        return new self($name, PostCount::of(0));
    }
}
