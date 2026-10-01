<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * A todo as an HTML page: a body that is not JSON, so it has to reach the connection as it is.
 */
final readonly class TodoPage implements ApiResponse
{
    public function __construct(
        private Todo $todo,
    ) {}

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(200);
    }

    public static function description(): string
    {
        return 'The todo as HTML';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::builtin(BuiltinType::string);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('text/html; charset=utf-8');
    }

    public function body(): string
    {
        return '<h1 class="todo">' . htmlspecialchars($this->todo->title) . '</h1>';
    }
}
