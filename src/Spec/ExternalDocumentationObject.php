<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#external-documentation-object
 */
final readonly class ExternalDocumentationObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public string $url,
        public string|null $description = null,
    ) {}
}
