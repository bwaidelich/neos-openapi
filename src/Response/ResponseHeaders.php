<?php

declare(strict_types=1);

namespace Neos\OpenApi\Response;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * The headers one {@see ApiResponseWithHeaders} declares.
 *
 * Not a spec model object and deliberately not `JsonSerializable`: the compiler turns it into a
 * {@see HeaderOrReferenceObjectMap} of {@see HeaderObject}s, resolving each declared {@see TypeReference} into a
 * schema on the way — which is a thing only the compiler, holding the {@see \Neos\OpenApi\Binding\TypeBinding}s and the
 * {@see SchemaComponents}, can do.
 *
 * Names are unique **case-insensitively**, because HTTP field names are: declaring `ETag` and `etag` would be one
 * header declared twice, and the second would silently overwrite the first on the wire.
 *
 * @implements IteratorAggregate<int, ResponseHeader>
 */
final readonly class ResponseHeaders implements IteratorAggregate
{
    /**
     * @var list<ResponseHeader>
     */
    private array $items;

    private function __construct(ResponseHeader ...$items)
    {
        $seen = [];
        foreach ($items as $item) {
            $key = strtolower($item->name);
            if (isset($seen[$key])) {
                throw new \InvalidArgumentException(sprintf(
                    'Duplicate response header "%s" (field names are case-insensitive, and "%s" was already declared)',
                    $item->name,
                    $seen[$key],
                ), 1783500212);
            }
            $seen[$key] = $item->name;
        }
        $this->items = array_values($items);
    }

    public static function create(ResponseHeader ...$items): self
    {
        return new self(...$items);
    }

    public function with(ResponseHeader $header): self
    {
        return new self(...[...$this->items, $header]);
    }

    /**
     * The header declared under this name, matched case-insensitively, or `null` if none is.
     */
    public function get(string $name): ResponseHeader|null
    {
        foreach ($this->items as $item) {
            if (strcasecmp($item->name, $name) === 0) {
                return $item;
            }
        }
        return null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
