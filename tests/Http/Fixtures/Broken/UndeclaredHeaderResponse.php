<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures\Broken;

use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponseWithHeaders;
use Neos\OpenApi\Response\ResponseHeader;
use Neos\OpenApi\Response\ResponseHeaders;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * Returns a header it never declared — which the document therefore does not describe.
 */
final readonly class UndeclaredHeaderResponse implements ApiResponseWithHeaders
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
            ResponseHeader::create('X-Declared', TypeReference::builtin(BuiltinType::string)),
        );
    }

    public function body(): null
    {
        return null;
    }

    public function headers(): array
    {
        return ['X-Declared' => 'yes', 'X-Undeclared' => 'no'];
    }
}
