<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * A shape deriving its schema from its own constructor, with the value objects it holds embedding theirs.
 *
 * Analysis returns the `Schema` interface, so a class wanting to add a `description` writes its `ObjectSchema`
 * out instead of decorating the derived one.
 */
final readonly class Author implements ProvidesSchema
{
    public function __construct(
        public AuthorName $name,
        public PostCount $posts,
        public AuthorName|null $pseudonym = null,
    ) {}

    public static function of(AuthorName $name): self
    {
        return new self($name, PostCount::of(0));
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
