<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

/**
 * The one OpenAPI version this package speaks.
 *
 * Deliberately a constant rather than a value object with a range of accepted versions: OpenAPI 3.1.x *is* JSON
 * Schema 2020-12, so the `neos/jsonschema` objects drop straight into a document unchanged, while 3.0.x uses a
 * divergent dialect (`nullable`, boolean `exclusiveMinimum`, no sibling keywords next to `$ref`) that would need a
 * lossy translation layer maintained forever. See [ADR 0001](../docs/adr/0001-openapi-31-only.md).
 *
 * The predecessor `wwwision/types-openapi` defaulted to `3.0.3` and let you choose, so a reader coming from it
 * will look for the choice and find none. That is the decision, not an omission.
 */
final class SpecVersion
{
    public const VALUE = '3.1.1';

    /**
     * The JSON Schema dialect an OpenAPI 3.1 document uses unless it says otherwise.
     */
    public const JSON_SCHEMA_DIALECT = 'https://json-schema.org/draft/2020-12/schema';

    private function __construct()
    {
        // not instantiable: this is a namespace for constants, not a value
    }
}
