<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\Schematic\Attributes\IntegerBased;

#[IntegerBased(minimum: 0)]
final readonly class PostCount
{
    private function __construct(public int $value) {}

    public static function of(int $value): self
    {
        return new self($value);
    }
}
