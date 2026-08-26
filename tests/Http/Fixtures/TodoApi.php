<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\OpenApi\Attributes\AuthContext;
use Neos\OpenApi\Attributes\Operation;
use Neos\OpenApi\Attributes\Parameter;
use Neos\OpenApi\Attributes\RequestBody;
use Neos\OpenApi\Support\ParameterLocation;

/**
 * The API the request handler is driven against — every argument source, both response shapes, and an operation
 * that answers nothing at all.
 */
final class TodoApi
{
    /**
     * Records what the last invocation was handed, so a test can assert the *arguments* rather than only the
     * response they produced.
     *
     * @var array<string, mixed>
     */
    public array $lastArguments = [];

    #[Operation(path: '/todos/{id}', method: 'GET')]
    public function getTodo(TodoId $id): Todo|TodoNotFound
    {
        $this->lastArguments = ['id' => $id];
        return $id->value === 'missing' ? new TodoNotFound() : Todo::create($id, 'Write the handler');
    }

    #[Operation(path: '/todos', method: 'GET')]
    public function listTodos(
        int $limit = 2,
        #[Parameter(in: ParameterLocation::header, name: 'X-Client-Id')]
        string|null $client = null,
    ): Todos {
        $this->lastArguments = ['limit' => $limit, 'client' => $client];
        $todos = [];
        for ($index = 1; $index <= $limit; $index++) {
            $todos[] = Todo::create(TodoId::create('todo-' . $index), 'Todo ' . $index);
        }
        return Todos::of(...$todos);
    }

    #[Operation(path: '/todos', method: 'POST', security: 'bearerAuth')]
    public function createTodo(
        #[RequestBody]
        NewTodo $todo,
        #[AuthContext]
        Caller $caller,
    ): TodoCreated {
        $this->lastArguments = ['todo' => $todo, 'caller' => $caller];
        $created = Todo::create(TodoId::create('new'), $todo->title);
        // the optional headers are left out for one title, so a declared-but-unsent header is covered too
        return $todo->title === 'Quietly'
            ? new TodoCreated($created, tags: TodoTags::of())
            : new TodoCreated($created, rateLimitRemaining: 41, tags: TodoTags::of('fresh', 'unsorted'));
    }

    /**
     * Secured by a *basic* scheme rather than the bearer one above, so the challenge each implies is observable.
     */
    #[Operation(path: '/todos/archive', method: 'POST', security: 'basicAuth')]
    public function archiveTodos(): void
    {
        $this->lastArguments = ['archived' => true];
    }

    #[Operation(path: '/todos/{id}', method: 'DELETE')]
    public function deleteTodo(TodoId $id): void
    {
        $this->lastArguments = ['id' => $id];
    }

    /**
     * Two required query parameters, so a request missing both is rejected for both at once.
     */
    #[Operation(path: '/reports', method: 'GET')]
    public function report(TodoId $id, int $limit): Todo
    {
        $this->lastArguments = ['id' => $id, 'limit' => $limit];
        return Todo::create($id, 'Report');
    }
}
