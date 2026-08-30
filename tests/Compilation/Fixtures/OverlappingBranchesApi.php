<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\Operation;

final class OverlappingBranchesApi
{
    /**
     * A `DetailedReply` is a `Reply`, so a returned detailed one matches both branches — which is fine: the
     * first match renders it, and either would read the same properties out of the value.
     */
    #[Operation(path: '/replies', method: 'GET')]
    public function reply(bool $detailed = false): Reply|DetailedReply
    {
        return $detailed ? new DetailedReply('hello', 'at length') : new Reply('hello');
    }
}
