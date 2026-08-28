<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures\Colliding;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

final readonly class Rival implements ProvidesSchema
{
    public function __construct(public AuthorName $name) {}

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
