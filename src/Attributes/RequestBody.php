<?php

declare(strict_types=1);

namespace Neos\OpenApi\Attributes;

use Attribute;

/**
 * Marks the operation argument the request body is decoded into.
 *
 * Required, deliberately: the predecessor inferred the body positionally, so reordering a method signature
 * silently changed the shape of the published API. See ADR 0006.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class RequestBody
{
    public function __construct(
        public readonly string|null $description = null,
        public readonly string $contentType = 'application/json',
    ) {}
}
