<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\Schematic\Attributes\Discriminator;

#[Discriminator(propertyName: 'kind', mapping: ['text' => TextBlock::class, 'image' => ImageBlock::class])]
interface Block {}
