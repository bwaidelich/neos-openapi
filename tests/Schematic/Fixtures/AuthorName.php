<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\Schematic\Attributes\Description;
use Neos\Schematic\Attributes\StringBased;

#[Description('The full name of an author')]
#[StringBased(minLength: 1, maxLength: 200)]
final readonly class AuthorName
{
    private function __construct(public string $value) {}

    public static function of(string $value): self
    {
        return new self($value);
    }
}
