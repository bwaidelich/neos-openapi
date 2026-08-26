<?php

declare(strict_types=1);

namespace Neos\OpenApi\Response;

/**
 * An {@see ApiResponse} that carries headers of its own — a `Location` on a `201`, a `Retry-After` on a `429`.
 *
 * The split is the one `ApiResponse` already makes: **static** {@see self::headerTypes()} is what the generator
 * reads while compiling, when no instance exists, and instance {@see self::headers()} is what a request handler
 * writes onto the response. Neither half can drift from the other, because the runtime only ever emits headers
 * this class declared, through the same {@see TypeBinding} their schemas came from.
 *
 * A **separate** interface rather than two more members on `ApiResponse`, because almost no response has headers
 * and PHP has no default implementations: making every `404` write `return ResponseHeaders::create();` would be
 * boilerplate on the common case in order to serve the rare one. Implement this one instead, and both the
 * compiler and the handler pick it up.
 */
interface ApiResponseWithHeaders extends ApiResponse
{
    /**
     * The headers every instance of this response may carry, each with the type its value has.
     */
    public static function headerTypes(): ResponseHeaders;

    /**
     * The values to write, keyed by header name (matched case-insensitively against {@see self::headerTypes()}).
     *
     * Every value goes out through the binding its declared type names, exactly as {@see ApiResponse::body()}
     * does. A header declared *optional* may be left out or given `null`, and is then not sent; one this response
     * never declared, or a required one with no value, is a bug in the response class and fails loudly rather
     * than reaching a caller as an undocumented header or a missing one.
     *
     * @return array<string, mixed>
     */
    public function headers(): array;
}
