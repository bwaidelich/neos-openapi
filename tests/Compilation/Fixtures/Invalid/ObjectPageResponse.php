<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures\Invalid;

use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;
use Neos\OpenApi\Tests\Compilation\Fixtures\Post;

/**
 * An HTML response whose body is an object: there is nothing to write as it is.
 */
final readonly class ObjectPageResponse implements ApiResponse
{
    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(200);
    }

    public static function description(): string
    {
        return 'Not really HTML';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::of(Post::class);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('text/html');
    }

    public function body(): null
    {
        return null;
    }
}
