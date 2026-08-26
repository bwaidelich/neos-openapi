<?php

declare(strict_types=1);

namespace Neos\OpenApi\Support;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#security-scheme-object
 */
enum SecuritySchemeType: string
{
    case apiKey = 'apiKey';
    case http = 'http';
    case mutualTLS = 'mutualTLS';
    case oauth2 = 'oauth2';
    case openIdConnect = 'openIdConnect';
}
