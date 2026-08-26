<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\Schematic\Attributes\ListBased;

#[ListBased(itemClassName: AuthorName::class)]
final readonly class AuthorNames
{
    /**
     * @var list<AuthorName>
     */
    public array $names;

    private function __construct(AuthorName ...$names)
    {
        $this->names = array_values($names);
    }

    public static function of(AuthorName ...$names): self
    {
        return new self(...$names);
    }
}
