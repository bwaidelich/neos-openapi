<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures\Broken;

use Neos\OpenApi\Attributes\Operation;

/**
 * A response whose body contradicts the body type it declared, for a content type that is not JSON.
 */
final class BrokenBodyApi
{
    #[Operation(path: '/text', method: 'GET')]
    public function text(): NonStringBodyResponse
    {
        return new NonStringBodyResponse();
    }
}
