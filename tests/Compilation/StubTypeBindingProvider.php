<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation;

use Neos\OpenApi\Binding\TypeBinding;
use Neos\OpenApi\Binding\TypeBindingProvider;
use Neos\OpenApi\Binding\TypeReference;

/**
 * A {@see TypeBindingProvider} with no schema engine behind it at all.
 *
 * The compiler is *core*, so its tests have to prove it works against the port rather than against
 * `neos/schematic` — which is the promise ADR 0002 makes, and which the architecture test enforces. The real
 * adapter is exercised separately, in `tests/Schematic`.
 */
final readonly class StubTypeBindingProvider implements TypeBindingProvider
{
    public function for(TypeReference $type): TypeBinding
    {
        return new StubTypeBinding($type);
    }
}
