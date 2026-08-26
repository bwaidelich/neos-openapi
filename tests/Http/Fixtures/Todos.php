<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\JsonSchema\Validation\Issue;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\JsonSchema\Validation\Issues;
use Neos\OpenApi\Binding\CoercionOutcome;

final readonly class Todos implements Coercible
{
    /**
     * @var list<Todo>
     */
    private array $todos;

    private function __construct(Todo ...$todos)
    {
        $this->todos = array_values($todos);
    }

    public static function of(Todo ...$todos): self
    {
        return new self(...$todos);
    }

    public static function coerce(mixed $input): CoercionOutcome
    {
        if (!is_array($input)) {
            return CoercionOutcome::failed(Issues::create(Issue::create([], IssueCode::InvalidType, 'Not a list of todos')));
        }
        $todos = [];
        foreach ($input as $item) {
            $outcome = Todo::coerce($item);
            if (!$outcome->success) {
                return $outcome;
            }
            $todo = $outcome->value();
            assert($todo instanceof Todo);
            $todos[] = $todo;
        }
        return CoercionOutcome::ok(new self(...$todos));
    }

    /**
     * @return list<array{id: string, title: string, done: bool}>
     */
    public function serialize(): array
    {
        return array_map(static fn(Todo $todo): array => $todo->serialize(), $this->todos);
    }
}
