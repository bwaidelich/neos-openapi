<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

enum PostStatus: string implements ProvidesSchema
{
    case draft = 'draft';
    case published = 'published';

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
