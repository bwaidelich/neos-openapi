<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<string, ServerVariableObject>
 */
final readonly class ServerVariableObjects implements IteratorAggregate, JsonSerializable
{
    /**
     * @var array<string, ServerVariableObject>
     */
    private array $items;

    /**
     * @param array<string, ServerVariableObject> $items
     */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function create(): self
    {
        return new self([]);
    }

    public function with(string $name, ServerVariableObject $variable): self
    {
        $items = $this->items;
        $items[$name] = $variable;
        return new self($items);
    }

    public function defaultValueOf(string $name): string|null
    {
        return $this->items[$name]->default ?? null;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array<string, ServerVariableObject>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
