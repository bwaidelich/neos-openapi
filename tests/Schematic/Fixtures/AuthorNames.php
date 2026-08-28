<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

final readonly class AuthorNames implements ProvidesSchema
{
    /**
     * @var list<AuthorName>
     */
    public array $names;

    public function __construct(AuthorName ...$names)
    {
        $this->names = array_values($names);
    }

    public static function of(AuthorName ...$names): self
    {
        return new self(...$names);
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
