<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures\Broken;

use Neos\OpenApi\Attributes\Operation;

/**
 * Three ways a response class can contradict the headers it declared. Each one is a bug in the API, so the
 * handler raises rather than answering with a status the document never mentioned.
 */
final class BrokenHeaderApi
{
    #[Operation(path: '/undeclared', method: 'GET')]
    public function undeclared(): UndeclaredHeaderResponse
    {
        return new UndeclaredHeaderResponse();
    }

    #[Operation(path: '/missing', method: 'GET')]
    public function missing(): MissingHeaderResponse
    {
        return new MissingHeaderResponse();
    }

    #[Operation(path: '/unrenderable', method: 'GET')]
    public function unrenderable(): UnrenderableHeaderResponse
    {
        return new UnrenderableHeaderResponse();
    }
}
