<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\JsonSchema\Schema as JsonSchema;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * Like a Parameter Object, but its name comes from the map key and its location is always `header`.
 *
 * @see https://spec.openapis.org/oas/v3.1.1#header-object
 */
final readonly class HeaderObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public string|null $description = null,
        public bool|null $required = null,
        public bool|null $deprecated = null,
        public JsonSchema|null $schema = null,
    ) {}
}
