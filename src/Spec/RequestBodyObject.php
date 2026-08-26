<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * Carries no runtime metadata: which method argument this body feeds lives in the Dispatch Table, not here
 * (ADR 0003).
 *
 * @see https://spec.openapis.org/oas/v3.1.1#request-body-object
 */
final readonly class RequestBodyObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public MediaTypeObjectMap $content,
        public string|null $description = null,
        public bool|null $required = null,
    ) {
        if ($content->isEmpty()) {
            throw new \InvalidArgumentException('A Request Body Object must describe at least one media type', 1783500140);
        }
    }
}
