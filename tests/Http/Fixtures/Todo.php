<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

final readonly class Todo implements ProvidesSchema
{
    public function __construct(
        public TodoId $id,
        public string $title,
        public bool $done = false,
    ) {}

    public static function create(TodoId $id, string $title, bool $done = false): self
    {
        return new self($id, $title, $done);
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
