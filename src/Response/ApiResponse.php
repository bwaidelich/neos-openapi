<?php

declare(strict_types=1);

namespace Neos\OpenApi\Response;

use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * A return type that fixes its own HTTP status.
 *
 * Every member is **static** because the generator has to read them while compiling, when no instance exists. An
 * operation declares the responses it can produce as a union return type; a return type that is not an
 * ApiResponse is a `200`.
 *
 * `bodyType()` is what the predecessor lacked: there, a non-200 response could contribute a description but never
 * document a body. Returning a {@see TypeReference} rather than a raw schema keeps it going through the same port
 * as everything else, so an error body is described exactly the way a success body is.
 */
interface ApiResponse
{
    public static function statusCode(): HttpStatusCode;

    /**
     * The `description` member, which the specification requires of every response.
     */
    public static function description(): string;

    /**
     * The type of this response's body, or `null` for a response that has none.
     * TODO: Why not return Schema?
     */
    public static function bodyType(): TypeReference|null;

    /**
     * `null` for a response with no body; otherwise the media type its body is rendered as.
     */
    public static function contentType(): MediaTypeRange|null;

    /**
     * The value to render as this response's body — an instance of whatever {@see self::bodyType()} names, or
     * `null` for a response that documents none.
     *
     * The one *instance* member of the interface, and the only one that has to be: everything the generator asks
     * is asked without an instance, but a request handler has one, and it has to get the payload out of it. The
     * value goes out through the same {@see TypeBinding} the schema came from, so a response body cannot drift
     * away from the schema published for it.
     */
    public function body(): mixed;
}
