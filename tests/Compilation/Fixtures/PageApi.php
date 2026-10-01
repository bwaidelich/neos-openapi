<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\Operation;

final class PageApi
{
    #[Operation(path: '/posts/{slug}/page', method: 'GET')]
    public function page(PostSlug $slug): PostPage
    {
        return new PostPage(PostTitle::create($slug->value));
    }
}
