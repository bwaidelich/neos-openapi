<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

final readonly class PostSlug
{
    private function __construct(public string $value) {}

    public static function create(string $value): self
    {
        return new self($value);
    }
}
