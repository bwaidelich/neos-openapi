<?php

declare(strict_types=1);

namespace Neos\OpenApi\Response;

use Neos\OpenApi\Support\MediaTypeRange;

/**
 * An {@see ApiResponse} whose body is not one value but a sequence written to the connection as it becomes
 * available — Server-Sent Events, a chunked download, anything that does not exist all at once when the response
 * starts.
 *
 * `bodyType()` and `body()` are narrowed to `null`: there is no single schema for "the complete content" of a
 * stream, and no single value either — {@see self::stream()} is what a request handler reads from instead, and
 * neither of these is ever called for one. Narrowing them here, rather than documenting the convention and
 * checking it while compiling, turns a `StreamResponse` that tries to declare a body into a PHP type error at the
 * point it is defined, instead of an `InvalidApiDefinitionException` raised only once something compiles it.
 *
 * Unlike {@see ApiResponse::contentType()}, this interface's is **not** nullable — a stream always has to say what
 * it is streaming.
 *
 * `stream()` yields raw `string` chunks, written to the connection as-is, or {@see SseEvent}s — the request handler
 * renders each one through the same {@see \Neos\OpenApi\Binding\TypeBinding} everything else in this package goes
 * through, so a typed event's data cannot drift from the document any more than an ordinary body can (ADR 0005).
 *
 * @see \Neos\OpenApi\Http\GeneratorStream what this is served through
 */
interface StreamResponse extends ApiResponse
{
    public static function bodyType(): null;

    public static function contentType(): MediaTypeRange;

    public function body(): null;

    /**
     * @return \Generator<int, string|SseEvent>
     */
    public function stream(): \Generator;
}
