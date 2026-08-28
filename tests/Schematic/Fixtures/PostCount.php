<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Schematic;

final readonly class PostCount implements ProvidesSchema
{
    private function __construct(public int $value) {}

    public static function of(int $value): self
    {
        return Schematic::instantiate(self::class, $value);
    }

    public static function fromInteger(int $value): self
    {
        return self::of($value);
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= IntegerSchema::create(minimum: 0);
    }
}
