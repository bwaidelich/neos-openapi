<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\Operation;

final class StreamApi
{
    #[Operation(path: '/heartbeat', method: 'GET')]
    public function heartbeat(): HeartbeatStream
    {
        return new HeartbeatStream();
    }
}
