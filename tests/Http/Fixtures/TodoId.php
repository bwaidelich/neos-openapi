<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\Schematic\Schematic;

final readonly class TodoId implements ProvidesSchema
{
    private function __construct(
        public string $value,
    ) {}

    public static function create(string $value): self
    {
        return self::fromString($value);
    }

    public static function fromString(string $value): self
    {
        return Schematic::instanciate(self::class, $value)->valueOrThrow();
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= StringSchema::create(pattern: '^[a-z0-9-]+$');
    }
}
