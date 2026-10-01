<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures\Broken;

use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * Declares a plain text body but hands over a number: nothing to write as it is.
 */
final readonly class NonStringBodyResponse implements ApiResponse
{
    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(200);
    }

    public static function description(): string
    {
        return 'Not really text';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::builtin(BuiltinType::string);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('text/plain');
    }

    public function body(): int
    {
        return 42;
    }
}
