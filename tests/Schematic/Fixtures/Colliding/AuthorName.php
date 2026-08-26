<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures\Colliding;

use Neos\Schematic\Attributes\StringBased;

/**
 * A second `AuthorName`, in another namespace — so two classes claim the component name `AuthorName`.
 */
#[StringBased(minLength: 1)]
final readonly class AuthorName
{
    private function __construct(public string $value) {}
}
