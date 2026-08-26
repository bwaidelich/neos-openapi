<?php

declare(strict_types=1);

namespace Neos\OpenApi\Support;

/**
 * Where an `apiKey` security scheme expects to find its key. Narrower than {@see ParameterLocation}: the
 * specification does not allow `path` here.
 */
enum SecuritySchemeApiKeyLocation: string
{
    case query = 'query';
    case header = 'header';
    case cookie = 'cookie';
}
