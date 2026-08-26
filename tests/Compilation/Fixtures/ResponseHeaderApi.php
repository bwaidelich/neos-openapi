<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\OpenApi\Attributes\Operation;
use Neos\OpenApi\Attributes\RequestBody;

final class ResponseHeaderApi
{
    #[Operation(path: '/drafts', method: 'POST')]
    public function createDraft(
        #[RequestBody]
        NewPost $post,
    ): CreatedResponse {
        $slug = PostSlug::create('a-draft');
        return new CreatedResponse(Post::create($slug, $post->title), $slug, 41);
    }
}
