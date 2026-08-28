<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

final readonly class Todos implements ProvidesSchema
{
    /**
     * @var list<Todo>
     */
    public array $todos;

    public function __construct(Todo ...$todos)
    {
        $this->todos = array_values($todos);
    }

    public static function of(Todo ...$todos): self
    {
        return new self(...$todos);
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
