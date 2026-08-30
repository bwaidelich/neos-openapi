<?php

declare(strict_types=1);

namespace Neos\OpenApi\Dispatch;

/**
 * Where a request handler reads an operation argument from.
 */
enum ArgumentSource: string
{
    case path = 'path';
    case query = 'query';
    case header = 'header';
    case cookie = 'cookie';
    case body = 'body';
    /**
     * Not from the request at all — the caller's identity, from whatever authenticated them.
     */
    case authContext = 'authContext';
    /**
     * The whole request, handed over unread — for the operation whose contract is not a set of named parameters.
     */
    case request = 'request';
}
