<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\SseEvent;
use Neos\OpenApi\Response\TypedStreamResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

final readonly class CommentStream implements TypedStreamResponse
{
    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(200);
    }

    public static function description(): string
    {
        return 'A live feed of comments';
    }

    public static function bodyType(): null
    {
        return null;
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('text/event-stream');
    }

    public static function itemType(): TypeReference
    {
        return TypeReference::builtin(BuiltinType::string);
    }

    public function body(): null
    {
        return null;
    }

    public function stream(): \Generator
    {
        yield SseEvent::forData(TypeReference::builtin(BuiltinType::string), 'Hello', name: 'comment');
        yield SseEvent::forData(TypeReference::builtin(BuiltinType::string), 'World', name: 'comment');
    }
}
