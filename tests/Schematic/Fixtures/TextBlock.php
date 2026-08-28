<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * A plain shape with no schema of its own: analysis derives one from the constructor.
 */
final readonly class TextBlock implements ProvidesSchema
{
    public function __construct(public AuthorName $body) {}

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
