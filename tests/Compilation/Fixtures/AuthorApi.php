<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\Operation;

final class AuthorApi
{
    /**
     * Deliberately also called `listPosts` — an operationId collision across two Api Classes.
     */
    #[Operation(path: '/authors', method: 'GET')]
    public function listPosts(): PostTitle
    {
        return PostTitle::create('An author');
    }
}
