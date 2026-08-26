<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<string, HeaderObject|ReferenceObject>
 */
final readonly class HeaderOrReferenceObjectMap implements IteratorAggregate, JsonSerializable
{
    /**
     * @var array<string, HeaderObject|ReferenceObject>
     */
    private array $items;

    /**
     * @param array<string, HeaderObject|ReferenceObject> $items
     */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function create(): self
    {
        return new self([]);
    }

    public function with(string $name, HeaderObject|ReferenceObject $object): self
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
     * @return array<string, HeaderObject|ReferenceObject>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
