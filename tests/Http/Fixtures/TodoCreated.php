<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponseWithHeaders;
use Neos\OpenApi\Response\ResponseHeader;
use Neos\OpenApi\Response\ResponseHeaders;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * A `201` that says where the thing it created lives — the case response headers exist for, plus an optional one
 * so a header that is simply not sent is covered too.
 */
final readonly class TodoCreated implements ApiResponseWithHeaders
{
    public function __construct(
        private Todo $todo,
        private int|null $rateLimitRemaining = null,
        private TodoTags|null $tags = null,
    ) {}

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(201);
    }

    public static function description(): string
    {
        return 'The todo was created';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::of(Todo::class);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('application/json');
    }

    public static function headerTypes(): ResponseHeaders
    {
        return ResponseHeaders::create(
            ResponseHeader::create('Location', TypeReference::builtin(BuiltinType::string), description: 'Where the new todo lives'),
            ResponseHeader::create('X-Rate-Limit-Remaining', TypeReference::builtin(BuiltinType::int), required: false),
            ResponseHeader::create('X-Todo-Tags', TypeReference::of(TodoTags::class), required: false),
        );
    }

    public function body(): Todo
    {
        return $this->todo;
    }

    public function headers(): array
    {
        return [
            'Location' => '/todos/' . $this->todo->id->value,
            'X-Rate-Limit-Remaining' => $this->rateLimitRemaining,
            'X-Todo-Tags' => $this->tags,
        ];
    }
}
