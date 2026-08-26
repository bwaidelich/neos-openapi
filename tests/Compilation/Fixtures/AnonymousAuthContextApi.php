<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\AuthContext;
use Neos\OpenApi\Attributes\Operation;

/**
 * Authentication is optional here, so there may be no caller at all — yet the argument insists on one.
 */
final class AnonymousAuthContextApi
{
    #[Operation(path: '/posts', method: 'GET', security: 'bearerAuth', allowAnonymous: true)]
    public function listPosts(#[AuthContext] Caller $caller): PostTitle
    {
        return PostTitle::create('A post');
    }
}
