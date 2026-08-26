<?php

declare(strict_types=1);

namespace Neos\OpenApi\Support;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#parameter-object
 */
enum ParameterLocation: string
{
    case query = 'query';
    case header = 'header';
    case path = 'path';
    case cookie = 'cookie';
}
