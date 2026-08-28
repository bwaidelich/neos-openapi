<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * A domain type of the API's own, handed over as the authenticated caller — note it implements nothing from
 * `neos/openapi`, and carries no schema-engine attributes either.
 */
final readonly class Caller implements ProvidesSchema
{
    private function __construct(public string $value) {}

    public static function create(string $value): self
    {
        return new self($value);
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
