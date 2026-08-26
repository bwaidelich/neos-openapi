<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Keyed by the property name of the request body it encodes.
 *
 * @implements IteratorAggregate<string, EncodingObject>
 */
final readonly class EncodingObjectMap implements IteratorAggregate, JsonSerializable
{
    /**
     * @var array<string, EncodingObject>
     */
    private array $items;

    /**
     * @param array<string, EncodingObject> $items
     */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function create(): self
    {
        return new self([]);
    }

    public function with(string $propertyName, EncodingObject $object): self
    {
        $items = $this->items;
        $items[$propertyName] = $object;
        return new self($items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array<string, EncodingObject>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
