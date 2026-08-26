<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

/**
 * A domain type of the API's own, handed over as the authenticated caller — note it implements nothing from
 * `neos/openapi`, and carries no schema-engine attributes either.
 */
final readonly class Caller
{
    private function __construct(public string $value) {}

    public static function create(string $value): self
    {
        return new self($value);
    }
}
