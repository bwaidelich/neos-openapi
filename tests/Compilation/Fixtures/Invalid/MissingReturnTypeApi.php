<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures\Invalid;

use Neos\OpenApi\Attributes\Operation;

final class MissingReturnTypeApi
{
    #[Operation(path: '/posts', method: 'GET')]
    public function listPosts()
    {
        return null;
    }
}
