<?php

declare(strict_types=1);

namespace Neos\OpenApi\Dispatch;

/**
 * One argument, after compilation has worked out where its value comes from: the runtime {@see ArgumentBinding}
 * plus the documentation-only extras that belong in the published document but not in the Dispatch Table.
 *
 * @internal produced and consumed by {@see \Neos\OpenApi\Compilation\ApiCompiler}
 */
final readonly class ClassifiedArgument
{
    public function __construct(
        public ArgumentBinding $binding,
        public ArgumentSource $source,
        public string|null $description = null,
        public bool|null $deprecated = null,
        public string|null $contentType = null,
    ) {}
}
