<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\Operation;

final class OptionalPathParameterApi
{
    #[Operation(path: '/posts/{slug}', method: 'GET')]
    public function getPost(PostSlug|null $slug = null): PostTitle
    {
        return PostTitle::create('A post');
    }
}
