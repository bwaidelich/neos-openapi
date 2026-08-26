<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Neos\OpenApi\Support\HttpStatusCode;
use Traversable;

/**
 * Status codes to the responses they describe, plus an optional `default`.
 *
 * Entries are kept in ascending status-code order with `default` last, so a rendered document reads predictably
 * regardless of the order the generator happened to discover responses in.
 *
 * Note that PHP turns the numeric key `'200'` into the integer `200`, hence the `int|string` key type. It makes no
 * difference to the rendered document — a map whose keys are not a 0-based sequence still encodes as a JSON
 * object — and lookups normalise the same way, so `array_key_exists('200', …)` still finds it.
 *
 * @implements IteratorAggregate<int|string, ResponseObject|ReferenceObject>
 */
final readonly class ResponsesObject implements IteratorAggregate, JsonSerializable
{
    private const DEFAULT_KEY = 'default';

    /**
     * @var array<int|string, ResponseObject|ReferenceObject>
     */
    private array $items;

    /**
     * @param array<int|string, ResponseObject|ReferenceObject> $items
     */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function create(): self
    {
        return new self([]);
    }

    public function with(HttpStatusCode $statusCode, ResponseObject|ReferenceObject $object): self
    {
        $items = $this->items;
        $items[(string) $statusCode->value] = $object;
        return new self(self::sorted($items));
    }

    public function withDefault(ResponseObject|ReferenceObject $object): self
    {
        $items = $this->items;
        $items[self::DEFAULT_KEY] = $object;
        return new self(self::sorted($items));
    }

    public function hasResponseForStatusCode(HttpStatusCode $statusCode): bool
    {
        return array_key_exists((string) $statusCode->value, $this->items);
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
     * @return array<int|string, ResponseObject|ReferenceObject>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    /**
     * @param array<int|string, ResponseObject|ReferenceObject> $items
     * @return array<int|string, ResponseObject|ReferenceObject>
     */
    private static function sorted(array $items): array
    {
        uksort($items, static function (int|string $left, int|string $right): int {
            // `default` is not a number, and belongs after every explicit code
            return match (true) {
                $left === self::DEFAULT_KEY => 1,
                $right === self::DEFAULT_KEY => -1,
                default => (int) $left <=> (int) $right,
            };
        });
        return $items;
    }
}
