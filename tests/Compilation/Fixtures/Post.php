<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

final readonly class Post implements ProvidesSchema
{
    private function __construct(
        public PostSlug $slug,
        public PostTitle $title,
    ) {}

    public static function create(PostSlug $slug, PostTitle $title): self
    {
        return new self($slug, $title);
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
