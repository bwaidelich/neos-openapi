<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures\Broken;

use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponseWithHeaders;
use Neos\OpenApi\Response\ResponseHeader;
use Neos\OpenApi\Response\ResponseHeaders;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;
use Neos\OpenApi\Tests\Http\Fixtures\Todo;
use Neos\OpenApi\Tests\Http\Fixtures\TodoId;

/**
 * Types a header by something that serializes to a whole object — which a header field has no way to carry.
 */
final readonly class UnrenderableHeaderResponse implements ApiResponseWithHeaders
{
    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(202);
    }

    public static function description(): string
    {
        return 'Accepted';
    }

    public static function bodyType(): TypeReference|null
    {
        return null;
    }

    public static function contentType(): MediaTypeRange|null
    {
        return null;
    }

    public static function headerTypes(): ResponseHeaders
    {
        return ResponseHeaders::create(
            ResponseHeader::create('X-Todo', TypeReference::of(Todo::class)),
        );
    }

    public function body(): null
    {
        return null;
    }

    public function headers(): array
    {
        return ['X-Todo' => Todo::create(TodoId::create('one'), 'Not a header value')];
    }
}
