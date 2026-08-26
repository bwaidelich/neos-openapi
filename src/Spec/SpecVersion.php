<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

/**
 * The OpenAPI version(s) this package speaks: `3.1.1` by default, and `3.2.0` when a document needs a field 3.1
 * has no equivalent for.
 *
 * Deliberately constants rather than a value object with an arbitrary range of accepted versions: OpenAPI 3.1.x
 * *is* JSON Schema 2020-12, so the `neos/jsonschema` objects drop straight into a document unchanged, while 3.0.x
 * uses a divergent dialect (`nullable`, boolean `exclusiveMinimum`, no sibling keywords next to `$ref`) that would
 * need a lossy translation layer maintained forever.
 *
 * The predecessor defaulted to `3.0.3` and let you choose, so a reader coming from it will look for the choice
 * and find none. That is the decision, not an omission — {@see self::ITEM_SCHEMA_VALUE} is not a choice either:
 * {@see \Neos\OpenApi\Compilation\ApiCompiler} selects it itself, only when it has to.
 */
final class SpecVersion
{
    public const VALUE = '3.1.1';

    /**
     * `itemSchema` on a Media Type Object — describing the shape of each item of a
     * {@see \Neos\OpenApi\Response\TypedStreamResponse}, such as a Server-Sent Event — is an OpenAPI 3.2 field with
     * no 3.1 equivalent. A document containing one advertises this version instead of {@see self::VALUE};
     * every other document still advertises `3.1.1`, exactly as before.
     */
    public const ITEM_SCHEMA_VALUE = '3.2.0';

    /**
     * The JSON Schema dialect an OpenAPI 3.1 document uses unless it says otherwise.
     */
    public const JSON_SCHEMA_DIALECT = 'https://json-schema.org/draft/2020-12/schema';

    private function __construct()
    {
        // not instantiable: this is a namespace for constants, not a value
    }
}
