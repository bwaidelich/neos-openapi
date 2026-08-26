<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Neos\JsonSchema\ReferenceSchema;
use Neos\JsonSchema\Schema as JsonSchema;
use Traversable;

/**
 * The `components.schemas` map: Component names to the JSON Schemas they name.
 *
 * This is where the hoisting walker puts every schema it lifts out of an operation, so a type used twice is one
 * entry here referenced twice rather than two identical inline copies.
 *
 * @implements IteratorAggregate<string, JsonSchema>
 */
final readonly class SchemaObjectMap implements IteratorAggregate, JsonSerializable
{
    /**
     * @var array<string, JsonSchema>
     */
    private array $items;

    /**
     * @param array<string, JsonSchema> $items
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
     * A reference *to* an entry of this map, for use wherever a schema is expected.
     *
     * This is the counterpart of hoisting: the walker puts a schema in here under `$name` and leaves one of these
     * behind at the use site. It returns a `Neos\JsonSchema\ReferenceSchema` rather than an OpenAPI
     * {@see ReferenceObject}, because the position it goes into holds a schema.
     */
    public static function reference(string $name): ReferenceSchema
    {
        return ReferenceSchema::create('#/components/schemas/' . $name);
    }

    public function with(string $name, JsonSchema $schema): self
    {
        $items = $this->items;
        $items[$name] = $schema;
        return new self($items);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->items);
    }

    public function get(string $name): JsonSchema|null
    {
        return $this->items[$name] ?? null;
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
     * @return array<string, JsonSchema>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
