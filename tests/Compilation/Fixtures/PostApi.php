<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\AuthContext;
use Neos\OpenApi\Attributes\Operation;
use Neos\OpenApi\Attributes\Parameter;
use Neos\OpenApi\Attributes\RequestBody;
use Neos\OpenApi\Support\ParameterLocation;

final class PostApi
{
    #[Operation(path: '/posts', method: 'GET', summary: 'List posts')]
    public function listPosts(int $page = 1, string|null $search = null): Post
    {
        return $this->post($search ?? 'first');
    }

    #[Operation(path: '/posts/{slug}', method: 'GET')]
    public function getPost(PostSlug $slug): Post|NotFoundResponse
    {
        return $slug->value === 'missing' ? new NotFoundResponse() : $this->post($slug->value);
    }

    /**
     * A second method on `/posts` — the two must become two members of one Path Item Object.
     */
    #[Operation(path: '/posts', method: 'POST', security: 'bearerAuth')]
    public function createPost(
        #[RequestBody(description: 'The post to create')]
        NewPost $post,
        #[AuthContext]
        Caller $caller,
    ): Post|ConflictResponse {
        return $post->title->value === 'taken'
            ? new ConflictResponse(PostSlug::create('taken'))
            : $this->post($post->title->value);
    }

    #[Operation(path: '/posts/{slug}/views', method: 'GET')]
    public function views(
        PostSlug $slug,
        #[Parameter(in: ParameterLocation::header, name: 'X-Client-Id', description: 'Who is asking')]
        string $clientId,
    ): int {
        return 0;
    }

    #[Operation(path: '/health', method: 'GET')]
    public function health(): void {}

    public function notAnOperation(): void {}

    private function post(string $slug): Post
    {
        return Post::create(PostSlug::create($slug), PostTitle::create('A post'));
    }
}
