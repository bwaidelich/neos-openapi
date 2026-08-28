<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\OpenApi\Attributes\Operation;
use Neos\OpenApi\Attributes\RequestBody;

/**
 * A small but complete API, compiled through the real `neos/schematic` adapter — value objects, an enum, a list
 * and a nullable property all at once.
 */
final class BlogApi
{
    #[Operation(path: '/authors', method: 'GET', summary: 'List authors')]
    public function listAuthors(PostStatus|null $status = null): AuthorNames
    {
        return AuthorNames::of(AuthorName::of($status === null ? 'everyone' : $status->value));
    }

    #[Operation(path: '/authors/{name}', method: 'GET')]
    public function getAuthor(AuthorName $name): Author
    {
        return Author::of($name);
    }

    #[Operation(path: '/authors', method: 'POST')]
    public function addAuthor(#[RequestBody] AuthorName $name): AuthorCreated
    {
        return new AuthorCreated(Author::of($name));
    }
}
