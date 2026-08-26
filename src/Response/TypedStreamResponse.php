<?php

declare(strict_types=1);

namespace Neos\OpenApi\Response;

use Neos\OpenApi\Binding\TypeReference;

/**
 * A {@see StreamResponse} whose items share one shape, described in the document via `itemSchema` — an OpenAPI 3.2
 * field with no 3.1 equivalent, so a document containing one of these advertises `openapi: 3.2.0` instead of the
 * default `3.1.1` (ADR 0007).
 *
 * A **separate** interface from `StreamResponse`, the same way {@see ApiResponseWithHeaders} is separate from
 * {@see ApiResponse}: most streams — a chunked file download, for instance — have no single item shape to declare,
 * and forcing every one of them to implement `itemType()` just to return `null` would be exactly the boilerplate
 * that split avoided there.
 */
interface TypedStreamResponse extends StreamResponse
{
    public static function itemType(): TypeReference;
}
