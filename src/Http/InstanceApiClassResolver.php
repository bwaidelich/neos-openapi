<?php

declare(strict_types=1);

namespace Neos\OpenApi\Http;

/**
 * Resolves Api Classes from instances handed over up front — the whole of the wiring an API without a container
 * needs.
 */
final readonly class InstanceApiClassResolver implements ApiClassResolver
{
    /**
     * @var array<class-string, object>
     */
    private array $instances;

    public function __construct(object ...$instances)
    {
        $byClassName = [];
        foreach ($instances as $instance) {
            $byClassName[$instance::class] = $instance;
        }
        $this->instances = $byClassName;
    }

    public function resolve(string $className): object
    {
        return $this->instances[$className] ?? throw new \RuntimeException(sprintf(
            'No instance of the Api Class "%s" was provided',
            $className,
        ), 1783500401);
    }
}
