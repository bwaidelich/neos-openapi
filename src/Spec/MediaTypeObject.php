<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\JsonSchema\Schema as JsonSchema;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * The schema (and examples) of a payload for one media type.
 *
 * The `schema` is a `Neos\JsonSchema\Schema` rather than a replica of one: in OpenAPI 3.1 a Schema Object *is* a
 * JSON Schema 2020-12 schema, which is the whole reason this package targets 3.1.
 *
 * `itemSchema` — the shape of each item of a *sequential* media type such as `text/event-stream`, applied to each
 * item independently rather than to "the complete content" the way `schema` is — is an OpenAPI 3.2 field with no
 * 3.1 equivalent; a document using it advertises `openapi: 3.2.0`
 * ({@see \Neos\OpenApi\Spec\SpecVersion::ITEM_SCHEMA_VALUE}).
 *
 * @see https://spec.openapis.org/oas/v3.1.1#media-type-object
 */
final readonly class MediaTypeObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public JsonSchema|null $schema = null,
        public JsonSchema|null $itemSchema = null,
        public ExampleOrReferenceObjectMap|null $examples = null,
        public EncodingObjectMap|null $encoding = null,
    ) {}
}
