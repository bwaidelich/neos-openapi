<?php

declare(strict_types=1);

namespace Neos\OpenApi\Support;

use Psr\Container\ContainerInterface;

/**
 * A PSR-11 container whose entries are fixed at construction: objects handed over ready-made, keyed by their own
 * class name. It builds nothing on demand, which is what separates it from a container framework.
 *
 * It can be used if {@see \Neos\OpenApi\Http\RequestHandler} is used in an environment without PSR Container
 * existing.
 */
final readonly class FixedContainer implements ContainerInterface
{
    /**
     * @var array<class-string, object>
     */
    private array $entries;

    public function __construct(object ...$entries)
    {
        $byClassName = [];
        foreach ($entries as $entry) {
            $byClassName[$entry::class] = $entry;
        }
        $this->entries = $byClassName;
    }

    public function get(string $id): object
    {
        return $this->entries[$id] ?? throw ContainerEntryNotFoundException::for($id);
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }
}
