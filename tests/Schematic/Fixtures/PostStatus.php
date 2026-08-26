<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

enum PostStatus: string
{
    case draft = 'draft';
    case published = 'published';
}
