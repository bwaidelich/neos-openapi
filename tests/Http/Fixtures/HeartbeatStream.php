<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\OpenApi\Response\StreamResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * A stream with no declared item type — raw chunks, no `itemSchema`.
 */
final readonly class HeartbeatStream implements StreamResponse
{
    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(200);
    }

    public static function description(): string
    {
        return 'A single heartbeat';
    }

    public static function bodyType(): null
    {
        return null;
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('text/plain');
    }

    public function body(): null
    {
        return null;
    }

    public function stream(): \Generator
    {
        yield 'ping';
    }
}
