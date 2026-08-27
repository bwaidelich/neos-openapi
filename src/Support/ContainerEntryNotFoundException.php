<?php

declare(strict_types=1);

namespace Neos\OpenApi\Support;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown by {@see FixedContainer} when it was never given an instance of the requested class.
 *
 * A PSR-11 container has to answer an unknown identifier with a {@see NotFoundExceptionInterface}, which is what
 * this adds to an ordinary exception — nothing else.
 */
final class ContainerEntryNotFoundException extends \RuntimeException implements NotFoundExceptionInterface
{
    public static function for(string $className): self
    {
        return new self(sprintf('No instance of "%s" was provided', $className), 1783500401);
    }
}
