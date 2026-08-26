<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * The `parameters` member of the Components Object, which is a *map* — the predecessor reused the list type here,
 * which would have emitted a JSON array where the specification wants an object.
 *
 * @implements IteratorAggregate<string, ParameterObject|ReferenceObject>
 */
final readonly class ParameterOrReferenceObjectMap implements IteratorAggregate, JsonSerializable
{
    /**
     * @var array<string, ParameterObject|ReferenceObject>
     */
    private array $items;

    /**
     * @param array<string, ParameterObject|ReferenceObject> $items
     */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function create(): self
    {
        return new self([]);
    }

    public function with(string $name, ParameterObject|ReferenceObject $object): self
    {
        $items = $this->items;
        $items[$name] = $object;
        return new self($items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array<string, ParameterObject|ReferenceObject>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
