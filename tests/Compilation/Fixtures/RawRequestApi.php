<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\Operation;
use Psr\Http\Message\ServerRequestInterface;

/**
 * An operation whose contract is not a set of named parameters: it forwards every query parameter to something
 * else, so it asks for the request itself. Declared by type rather than by attribute - see
 * {@see \Neos\OpenApi\Compilation\ApiCompiler} on why.
 */
final class RawRequestApi
{
    #[Operation(path: '/search', method: 'GET', description: 'Every query parameter is forwarded verbatim.')]
    public function search(ServerRequestInterface $request, string $q): PostTitle
    {
        return PostTitle::create($q . ':' . count($request->getQueryParams()));
    }
}
