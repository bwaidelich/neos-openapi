<?php

declare(strict_types=1);

namespace Neos\OpenApi\Http;

use Psr\Container\ContainerInterface;

/**
 * Resolves Api Classes through a PSR-11 container, keyed by their class name.
 *
 * `psr/container` is a *suggested* dependency: everything else in this package works without it, and an
 * application wiring its Api Classes some other way only has to implement {@see ApiClassResolver}.
 */
final readonly class ContainerApiClassResolver implements ApiClassResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function resolve(string $className): object
    {
        $instance = $this->container->get($className);
        if (!$instance instanceof $className) {
            throw new \RuntimeException(sprintf(
                'The container returned %s for the Api Class "%s"',
                get_debug_type($instance),
                $className,
            ), 1783500400);
        }
        return $instance;
    }
}
