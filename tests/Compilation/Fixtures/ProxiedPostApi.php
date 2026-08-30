<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\AuthContext;
use Neos\OpenApi\Attributes\Operation;
use Neos\OpenApi\Attributes\Parameter;
use Neos\OpenApi\Attributes\RequestBody;
use Neos\OpenApi\Support\ParameterLocation;

/**
 * What a (e.g. Neos/Flow) proxy generator wraps: the class somebody wrote, with the attributes on its parameters.
 */
class WrittenPostApi
{
    #[Operation(path: '/posts', method: 'POST', security: 'bearerAuth')]
    public function create(
        #[RequestBody(description: 'The post to create')]
        PostTitle $post,
        #[AuthContext]
        Caller $caller,
        #[Parameter(in: ParameterLocation::header, name: 'X-Trace-Id')]
        string|null $trace = null,
    ): PostSlug {
        return PostSlug::create('a-post');
    }
}

/**
 * And what comes out: the method re-declared to wrap it, keeping the *method* attribute and losing the
 * parameters' ones - exactly what Flow's AOP weaving emits. Reflecting this class must still yield the API the
 * class above describes.
 */
final class ProxiedPostApi extends WrittenPostApi
{
    #[Operation(path: '/posts', method: 'POST', security: 'bearerAuth')]
    public function create(
        PostTitle $post,
        Caller $caller,
        string|null $trace = null,
    ): PostSlug {
        return parent::create($post, $caller, $trace);
    }
}
