<?php

declare(strict_types=1);

namespace Neos\OpenApi\Compilation;

use Neos\OpenApi\Dispatch\DispatchTable;
use Neos\OpenApi\Spec\OpenApiObject;

/**
 * What compiling an {@see ApiDefinition} produces: the document to publish, and the table to serve from.
 *
 * Two halves rather than one object carrying both roles, so publishing a specification and answering requests are
 * independent capabilities over one compilation. Both halves are plain data, so the whole thing can be cached.
 */
final readonly class CompiledApi
{
    public function __construct(
        public OpenApiObject $document,
        public DispatchTable $dispatchTable,
    ) {}
}
