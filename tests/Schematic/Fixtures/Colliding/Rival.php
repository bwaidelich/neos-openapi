<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures\Colliding;

final readonly class Rival
{
    private function __construct(public AuthorName $name) {}
}
