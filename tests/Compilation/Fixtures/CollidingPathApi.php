<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\Operation;

final class CollidingPathApi
{
    #[Operation(path: '/posts', method: 'GET')]
    public function alsoListsPosts(): PostTitle
    {
        return PostTitle::create('A post');
    }
}
