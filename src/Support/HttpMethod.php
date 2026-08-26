<?php

declare(strict_types=1);

namespace Neos\OpenApi\Support;

/**
 * The HTTP methods a Path Item Object can carry an operation for.
 *
 * The backing values are the method names as they appear on the wire; {@see self::specMember()} gives the
 * lowercase member name a Path Item Object uses for them.
 */
enum HttpMethod: string
{
    case GET = 'GET';
    case PUT = 'PUT';
    case POST = 'POST';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case HEAD = 'HEAD';
    case PATCH = 'PATCH';
    case TRACE = 'TRACE';

    /**
     * @return non-empty-lowercase-string
     */
    public function specMember(): string
    {
        return match ($this) {
            self::GET => 'get',
            self::PUT => 'put',
            self::POST => 'post',
            self::DELETE => 'delete',
            self::OPTIONS => 'options',
            self::HEAD => 'head',
            self::PATCH => 'patch',
            self::TRACE => 'trace',
        };
    }

    /**
     * Whether a request with this method is expected to carry a body.
     */
    public function allowsRequestBody(): bool
    {
        return match ($this) {
            self::POST, self::PUT, self::PATCH => true,
            default => false,
        };
    }
}
