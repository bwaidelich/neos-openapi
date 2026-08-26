<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\JsonSchema\Validation\Issue;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\JsonSchema\Validation\Issues;
use Neos\OpenApi\Binding\CoercionOutcome;

final readonly class Todo implements Coercible
{
    private function __construct(
        public TodoId $id,
        public string $title,
        public bool $done,
    ) {}

    public static function create(TodoId $id, string $title, bool $done = false): self
    {
        return new self($id, $title, $done);
    }

    public static function coerce(mixed $input): CoercionOutcome
    {
        if (!is_array($input) || !isset($input['id']) || !is_string($input['title'] ?? null)) {
            return CoercionOutcome::failed(Issues::create(Issue::create([], IssueCode::InvalidType, 'Not a todo')));
        }
        $id = TodoId::coerce($input['id']);
        if (!$id->success) {
            return $id;
        }
        $todoId = $id->value();
        assert($todoId instanceof TodoId);
        return CoercionOutcome::ok(new self($todoId, $input['title'], (bool) ($input['done'] ?? false)));
    }

    /**
     * @return array{id: string, title: string, done: bool}
     */
    public function serialize(): array
    {
        return ['id' => $this->id->value, 'title' => $this->title, 'done' => $this->done];
    }
}
