<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * The `components.securitySchemes` map: the scheme names a Security Requirement Object refers to.
 *
 * @implements IteratorAggregate<string, SecuritySchemeObject|ReferenceObject>
 */
final readonly class SecuritySchemeOrReferenceObjectMap implements IteratorAggregate, JsonSerializable
{
    /**
     * @var array<string, SecuritySchemeObject|ReferenceObject>
     */
    private array $items;

    /**
     * @param array<string, SecuritySchemeObject|ReferenceObject> $items
     */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function create(): self
    {
        return new self([]);
    }

    public function with(string $name, SecuritySchemeObject|ReferenceObject $object): self
    {
        $items = $this->items;
        $items[$name] = $object;
        return new self($items);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->items);
    }

    public function get(string $name): SecuritySchemeObject|ReferenceObject|null
    {
        return $this->items[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->items);
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
     * @return array<string, SecuritySchemeObject|ReferenceObject>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
