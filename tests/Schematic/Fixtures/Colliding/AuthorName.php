<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures\Colliding;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * A second `AuthorName`, in another namespace — so two classes claim the component name `AuthorName`.
 */
final readonly class AuthorName implements ProvidesSchema
{
    public function __construct(public string $value) {}

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
