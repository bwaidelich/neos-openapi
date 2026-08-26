<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\AuthContext;
use Neos\OpenApi\Attributes\Operation;

final class UnsecuredAuthContextApi
{
    #[Operation(path: '/posts', method: 'GET')]
    public function listPosts(#[AuthContext] Caller $caller): PostTitle
    {
        return PostTitle::create('A post');
    }
}
