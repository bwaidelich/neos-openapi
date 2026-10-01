<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures\Invalid;

use Neos\OpenApi\Attributes\Operation;

final class ObjectPageApi
{
    #[Operation(path: '/page', method: 'GET')]
    public function page(): ObjectPageResponse
    {
        return new ObjectPageResponse();
    }
}
