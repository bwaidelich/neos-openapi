<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

final readonly class NotFoundResponse implements ApiResponse
{
    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(404);
    }

    public static function description(): string
    {
        return 'No such post';
    }

    public static function bodyType(): TypeReference|null
    {
        return null;
    }

    public static function contentType(): MediaTypeRange|null
    {
        return null;
    }

    public function body(): null
    {
        return null;
    }
}
