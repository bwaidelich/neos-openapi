<?php

declare(strict_types=1);

namespace Neos\OpenApi\Dispatch;

use Neos\OpenApi\Support\HttpMethod;
use Neos\OpenApi\Support\RelativePath;

/**
 * The runtime half of a {@see \Neos\OpenApi\Compilation\CompiledApi}: a lookup from (path template, HTTP method) to the method
 * that answers it.
 *
 * Keyed by path *template*, not by request path — matching a concrete request to a template is the document's job
 * (`PathsObject::match()`), and this takes over once that is done.
 *
 * Plain data throughout: no closures, no reflection handles, no service references. A whole API therefore compiles
 * once and can be cached and served without reflecting anything.
 */
final readonly class DispatchTable
{
    /**
     * @var array<string, array<string, DispatchEntry>> path template => HTTP method value => entry
     */
    private array $entries;

    /**
     * @param array<string, array<string, DispatchEntry>> $entries
     */
    private function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    public static function create(): self
    {
        return new self([]);
    }

    public function with(RelativePath $path, HttpMethod $method, DispatchEntry $entry): self
    {
        $entries = $this->entries;
        $entries[$path->value][$method->value] = $entry;
        return new self($entries);
    }

    public function find(RelativePath $path, HttpMethod $method): DispatchEntry|null
    {
        return $this->entries[$path->value][$method->value] ?? null;
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
     * @return iterable<string, DispatchEntry> "METHOD /path" => entry, for diagnostics
     */
    public function all(): iterable
    {
        foreach ($this->entries as $path => $byMethod) {
            foreach ($byMethod as $method => $entry) {
                yield $method . ' ' . $path => $entry;
            }
        }
    }
}
