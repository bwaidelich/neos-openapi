<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * The `parameters` member of an Operation or Path Item Object — a *list*, unlike `components.parameters`.
 *
 * @implements IteratorAggregate<int, ParameterObject|ReferenceObject>
 */
final readonly class ParameterOrReferenceObjects implements IteratorAggregate, JsonSerializable
{
    /**
     * @var list<ParameterObject|ReferenceObject>
     */
    private array $items;

    public function __construct(ParameterObject|ReferenceObject ...$items)
    {
        $seen = [];
        foreach ($items as $item) {
            if (!$item instanceof ParameterObject) {
                continue;
            }
            // "A unique parameter is defined by a combination of a name and location"
            $key = $item->in->value . ':' . $item->name;
            if (isset($seen[$key])) {
                throw new \InvalidArgumentException(sprintf('Duplicate parameter "%s" in "%s"', $item->name, $item->in->value), 1783500150);
            }
            $seen[$key] = true;
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
     * @return list<ParameterObject|ReferenceObject>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
