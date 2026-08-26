<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponseWithHeaders;
use Neos\OpenApi\Response\ResponseHeader;
use Neos\OpenApi\Response\ResponseHeaders;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * A response that carries headers of its own — one typed by a value object, so it is documented by the same
 * component the body would use, and one optional builtin.
 */
final readonly class CreatedResponse implements ApiResponseWithHeaders
{
    public function __construct(
        private Post $post,
        private PostSlug $slug,
        private int|null $rateLimitRemaining = null,
    ) {}

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(201);
    }

    public static function description(): string
    {
        return 'The post was created';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::of(Post::class);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('application/json');
    }

    public static function headerTypes(): ResponseHeaders
    {
        return ResponseHeaders::create(
            ResponseHeader::create('X-Post-Slug', TypeReference::of(PostSlug::class), description: 'The slug it got'),
            ResponseHeader::create('X-Rate-Limit-Remaining', TypeReference::builtin(BuiltinType::int), required: false),
        );
    }

    public function body(): Post
    {
        return $this->post;
    }

    public function headers(): array
    {
        return ['X-Post-Slug' => $this->slug, 'X-Rate-Limit-Remaining' => $this->rateLimitRemaining];
    }
}
