<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\Operation;

final class UnaccountedArgumentApi
{
    /**
     * A POST argument that is neither a path parameter, nor #[Parameter], nor #[RequestBody]. The predecessor
     * would have silently made it the request body.
     */
    #[Operation(path: '/posts', method: 'POST')]
    public function createPost(NewPost $post): PostTitle
    {
        return PostTitle::create('A post');
    }
}
