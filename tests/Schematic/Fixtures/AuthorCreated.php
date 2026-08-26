<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponseWithHeaders;
use Neos\OpenApi\Response\ResponseHeader;
use Neos\OpenApi\Response\ResponseHeaders;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * A `201` whose header is typed by a domain value object: the document describes it with the component the engine
 * generated, and the wire value is what that same engine serializes it to.
 */
final readonly class AuthorCreated implements ApiResponseWithHeaders
{
    public function __construct(
        private Author $author,
    ) {}

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(201);
    }

    public static function description(): string
    {
        return 'The author was created';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::of(Author::class);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('application/json');
    }

    public static function headerTypes(): ResponseHeaders
    {
        return ResponseHeaders::create(
            ResponseHeader::create('X-Author-Name', TypeReference::of(AuthorName::class), description: 'Who was created'),
        );
    }

    public function body(): Author
    {
        return $this->author;
    }

    public function headers(): array
    {
        return ['X-Author-Name' => $this->author->name];
    }
}
