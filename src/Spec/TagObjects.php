<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<int, TagObject>
 */
final readonly class TagObjects implements IteratorAggregate, JsonSerializable
{
    /**
     * @var list<TagObject>
     */
    private array $items;

    public function __construct(TagObject ...$items)
    {
        $names = [];
        foreach ($items as $item) {
            if (isset($names[$item->name])) {
                throw new \InvalidArgumentException(sprintf('Duplicate tag "%s"', $item->name), 1783500120);
            }
            $names[$item->name] = true;
        }
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
     * @return list<TagObject>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
