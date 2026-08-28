<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * The API's own notion of who is calling — handed over by an {@see \Neos\OpenApi\Http\AuthContextProvider},
 * never built from the request.
 */
final readonly class Caller implements ProvidesSchema
{
    public function __construct(
        public string $name,
    ) {}

    public static function named(string $name): self
    {
        return new self($name);
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
