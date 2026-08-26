<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<int, ServerObject>
 */
final readonly class ServerObjects implements IteratorAggregate, JsonSerializable
{
    /**
     * @var list<ServerObject>
     */
    private array $items;

    public function __construct(ServerObject ...$items)
    {
        $this->items = array_values($items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return list<ServerObject>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
