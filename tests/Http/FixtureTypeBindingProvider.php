<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http;

use Neos\OpenApi\Binding\TypeBinding;
use Neos\OpenApi\Binding\TypeBindingProvider;
use Neos\OpenApi\Binding\TypeReference;

/**
 * A {@see TypeBindingProvider} with no schema engine behind it: the fixture types describe themselves, and the
 * builtins are handled here.
 *
 * The request handler is core, so proving it works has to mean proving it works against the *port* — the real
 * engine is exercised over the same ground in `tests/Schematic/RequestHandlingTest.php`.
 */
final readonly class FixtureTypeBindingProvider implements TypeBindingProvider
{
    public function for(TypeReference $type): TypeBinding
    {
        return new FixtureTypeBinding($type);
    }
}
