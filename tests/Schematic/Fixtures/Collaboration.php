<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * Two authors side by side: the same type used twice must become one component referenced twice.
 */
final readonly class Collaboration implements ProvidesSchema
{
    public function __construct(
        public Author $lead,
        public Author $second,
        public PostStatus $status,
    ) {}

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
