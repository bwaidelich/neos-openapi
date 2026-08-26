<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

final readonly class NewPost
{
    private function __construct(
        public PostTitle $title,
    ) {}
}
