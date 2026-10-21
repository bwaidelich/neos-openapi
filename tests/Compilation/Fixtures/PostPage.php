<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * A body that is not JSON, typed by a string-backed value object rather than a plain string.
 */
final readonly class PostPage implements ApiResponse
{
    public function __construct(
        private PostTitle $title,
    ) {}

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(200);
    }

    public static function description(): string
    {
        return 'The post as HTML';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::of(PostTitle::class);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('text/html');
    }

    public function body(): PostTitle
    {
        return $this->title;
    }
}
