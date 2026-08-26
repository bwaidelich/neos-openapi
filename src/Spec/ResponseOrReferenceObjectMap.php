<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Reusable responses under `components.responses`, keyed by component name.
 *
 * @implements IteratorAggregate<string, ResponseObject|ReferenceObject>
 */
final readonly class ResponseOrReferenceObjectMap implements IteratorAggregate, JsonSerializable
{
    /**
     * @var array<string, ResponseObject|ReferenceObject>
     */
    private array $items;

    /**
     * @param array<string, ResponseObject|ReferenceObject> $items
     */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function create(): self
    {
        return new self([]);
    }

    public function with(string $name, ResponseObject|ReferenceObject $object): self
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
     * @return array<string, ResponseObject|ReferenceObject>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
