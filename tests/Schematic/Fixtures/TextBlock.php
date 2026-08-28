<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

final readonly class TextBlock
{
    private function __construct(public AuthorName $body) {}
}
