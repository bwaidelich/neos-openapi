<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

/**
 * Two authors side by side: the same type used twice must become one component referenced twice.
 */
final readonly class Collaboration
{
    private function __construct(
        public Author $lead,
        public Author $second,
        public PostStatus $status,
    ) {}
}
