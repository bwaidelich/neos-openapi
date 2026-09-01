<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\Schematic\Schematic;

/**
 * A value object owning its schema, and handing over its own construction path.
 */
final readonly class AuthorName implements ProvidesSchema
{
    private function __construct(public string $value) {}

    public static function of(string $value): self
    {
        return Schematic::instanciate(self::class, $value)->valueOrThrow();
    }

    public static function fromString(string $value): self
    {
        return self::of($value);
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= StringSchema::create(description: 'The full name of an author', minLength: 1, maxLength: 200);
    }
}
