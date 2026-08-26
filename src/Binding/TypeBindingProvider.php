<?php

declare(strict_types=1);

namespace Neos\OpenApi\Binding;

use Neos\OpenApi\Exception\UnsupportedTypeException;

/**
 * Resolves a {@see TypeReference} to its {@see TypeBinding}.
 *
 * The seam this package's architecture rests on: core holds one of these and never learns what is behind it. The same provider is
 * used at compile time (to describe types) and at request time (to coerce and serialize them), which is what
 * guarantees the document and the runtime agree.
 *
 * Implementations are expected to be cheap to call repeatedly — the schema engine behind them caches.
 */
interface TypeBindingProvider
{
    /**
     * @throws UnsupportedTypeException if the type cannot be described
     */
    public function for(TypeReference $type): TypeBinding;
}
