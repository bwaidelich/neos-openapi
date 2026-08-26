<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Neos\OpenApi\Support\MediaTypeRange;
use Traversable;

/**
 * Media type ranges to the payloads they describe — the `content` member of a Request Body or Response Object.
 *
 * @implements IteratorAggregate<string, MediaTypeObject>
 */
final readonly class MediaTypeObjectMap implements IteratorAggregate, JsonSerializable
{
    /**
     * @var array<string, MediaTypeObject>
     */
    private array $items;

    /**
     * @param array<string, MediaTypeObject> $items
     */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function create(): self
    {
        return new self([]);
    }

    /**
     * The common case: a single `application/json` payload.
     */
    public static function json(MediaTypeObject $object): self
    {
        return self::create()->with(MediaTypeRange::fromString('application/json'), $object);
    }

    public function with(MediaTypeRange $mediaTypeRange, MediaTypeObject $object): self
    {
        $items = $this->items;
        $items[$mediaTypeRange->value] = $object;
        return new self($items);
    }

    /**
     * The entry describing the given media type: a concrete match wins over a wildcard one.
     */
    public function match(MediaTypeRange $mediaType): MediaTypeObject|null
    {
        $wildcardMatch = null;
        foreach ($this->items as $range => $object) {
            $candidate = MediaTypeRange::fromString($range);
            if (!$candidate->matches($mediaType)) {
                continue;
            }
            if ($candidate->isConcrete()) {
                return $object;
            }
            $wildcardMatch ??= $object;
        }
        return $wildcardMatch;
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
     * @return array<string, MediaTypeObject>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
