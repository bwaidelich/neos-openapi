<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * The reusable pieces a document refers to instead of repeating — above all `schemas`, which is where every
 * hoisted Component lands.
 *
 * @see https://spec.openapis.org/oas/v3.1.1#components-object
 */
final readonly class ComponentsObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public SchemaObjectMap|null $schemas = null,
        public ResponseOrReferenceObjectMap|null $responses = null,
        public ParameterOrReferenceObjectMap|null $parameters = null,
        public ExampleOrReferenceObjectMap|null $examples = null,
        public HeaderOrReferenceObjectMap|null $headers = null,
        public SecuritySchemeOrReferenceObjectMap|null $securitySchemes = null,
        public LinkOrReferenceObjectMap|null $links = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->jsonSerialize() === [];
    }
}
