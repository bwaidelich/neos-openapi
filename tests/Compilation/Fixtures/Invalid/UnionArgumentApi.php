<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures\Invalid;

use Neos\OpenApi\Attributes\Operation;
use Neos\OpenApi\Tests\Compilation\Fixtures\Post;
use Neos\OpenApi\Tests\Compilation\Fixtures\PostSlug;
use Neos\OpenApi\Tests\Compilation\Fixtures\PostTitle;

final class UnionArgumentApi
{
    /**
     * A union on the way *in*, which is the direction that has no answer: what arrives is primitives, and
     * choosing which branch to build them into is a question this package does not ask anyone.
     */
    #[Operation(path: '/posts/{id}', method: 'GET')]
    public function getPost(PostSlug|PostTitle $id): Post
    {
        return Post::create(PostSlug::create('a-post'), PostTitle::create('A post'));
    }
}
