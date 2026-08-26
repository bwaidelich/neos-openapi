<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * A non-200 response that *does* document a body — which the predecessor had no way to express.
 *
 * The return types are narrowed from the interface's nullable ones, which PHP allows and which says plainly that
 * this response always has a body.
 */
final readonly class ConflictResponse implements ApiResponse
{
    public function __construct(
        private PostSlug $slug,
    ) {}

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(409);
    }

    public static function description(): string
    {
        return 'That slug is taken';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::of(PostSlug::class);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('application/json');
    }

    public function body(): PostSlug
    {
        return $this->slug;
    }
}
