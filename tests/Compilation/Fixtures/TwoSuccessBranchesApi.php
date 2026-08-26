<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\Operation;

final class TwoSuccessBranchesApi
{
    /**
     * Two ordinary branches: each would become a 200, so only the last would survive in the document.
     */
    #[Operation(path: '/posts', method: 'GET')]
    public function listPosts(bool $detailed = false): Post|PostSlug
    {
        return $detailed
            ? Post::create(PostSlug::create('a-post'), PostTitle::create('A post'))
            : PostSlug::create('a-post');
    }
}
