<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\OpenApi\Attributes\Operation;

final class StreamApi
{
    #[Operation(path: '/comments', method: 'GET')]
    public function comments(): CommentStream
    {
        return new CommentStream();
    }

    #[Operation(path: '/heartbeat', method: 'GET')]
    public function heartbeat(): HeartbeatStream
    {
        return new HeartbeatStream();
    }
}
