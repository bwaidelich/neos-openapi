<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\Operation;

final class CommentApi
{
    #[Operation(path: '/comments', method: 'GET')]
    public function comments(): CommentStream
    {
        return new CommentStream();
    }
}
