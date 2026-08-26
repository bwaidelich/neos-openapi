<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#response-object
 */
final readonly class ResponseObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public string $description,
        public HeaderOrReferenceObjectMap|null $headers = null,
        public MediaTypeObjectMap|null $content = null,
        public LinkOrReferenceObjectMap|null $links = null,
    ) {}
}
