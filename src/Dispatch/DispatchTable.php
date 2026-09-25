<?php

declare(strict_types=1);

namespace Neos\OpenApi\Dispatch;

use IteratorAggregate;
use Neos\OpenApi\Support\HttpMethod;
use Neos\OpenApi\Support\RelativePath;
use Traversable;

/**
 * The runtime half of a {@see \Neos\OpenApi\Compilation\CompiledApi}: a lookup from (path template, HTTP method) —
 * or from an operationId — to the method that answers it.
 *
 * Keyed by path *template*, not by request path — matching a concrete request to a template is the document's job
 * (`PathsObject::match()`), and this takes over once that is done. A router that already knows which operation a
 * request is for can skip that step and look the entry up by its operationId instead.
 *
 * Plain data throughout: no closures, no reflection handles, no service references. A whole API therefore compiles
 * once and can be cached and served without reflecting anything.
 *
 * @implements IteratorAggregate<int, DispatchEntry>
 */
final readonly class DispatchTable implements IteratorAggregate
{
    /**
     * @param array<string, array<string, DispatchEntry>> $entries path template => HTTP method value => entry
     * @param array<string, DispatchEntry> $entriesByOperationId operationId => entry
     */
    private function __construct(
        private array $entries,
        private array $entriesByOperationId,
    ) {}

    public static function create(): self
    {
        return new self([], []);
    }

    /**
     * @throws \InvalidArgumentException if the entry's path and method, or its operationId, are already taken
     */
    public function with(DispatchEntry $entry): self
    {
        if ($this->has($entry->path, $entry->method)) {
            throw new \InvalidArgumentException(sprintf(
                'The Dispatch Table already has an entry for "%s %s"',
                $entry->method->value,
                $entry->path->value,
            ), 1783500501);
        }
        if (isset($this->entriesByOperationId[$entry->operationId])) {
            throw new \InvalidArgumentException(sprintf(
                'The Dispatch Table already has an entry for the operationId "%s"',
                $entry->operationId,
            ), 1783500502);
        }
        $entries = $this->entries;
        $entries[$entry->path->value][$entry->method->value] = $entry;
        $entriesByOperationId = $this->entriesByOperationId;
        $entriesByOperationId[$entry->operationId] = $entry;
        return new self($entries, $entriesByOperationId);
    }

    public function find(RelativePath $path, HttpMethod $method): DispatchEntry|null
    {
        return $this->entries[$path->value][$method->value] ?? null;
    }

    public function findByOperationId(string $operationId): DispatchEntry|null
    {
        return $this->entriesByOperationId[$operationId] ?? null;
    }

    public function has(RelativePath $path, HttpMethod $method): bool
    {
        return isset($this->entries[$path->value][$method->value]);
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * Every entry, in the order it was added
     *
     * @return Traversable<int, DispatchEntry>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->entriesByOperationId as $entry) {
            yield $entry;
        }
    }
}
