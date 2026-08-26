<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\JsonSchema\Validation\Issue;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\JsonSchema\Validation\Issues;
use Neos\OpenApi\Binding\CoercionOutcome;

final readonly class NewTodo implements Coercible
{
    private function __construct(
        public string $title,
    ) {}

    public static function coerce(mixed $input): CoercionOutcome
    {
        if (!is_array($input)) {
            return CoercionOutcome::failed(Issues::create(Issue::create([], IssueCode::InvalidType, 'Not an object')));
        }
        $title = $input['title'] ?? null;
        if (!is_string($title) || $title === '') {
            // located at "title", so the handler's prefixing is observable: it becomes "/body/title"
            return CoercionOutcome::failed(Issues::create(
                Issue::create(['title'], IssueCode::Required, 'A todo needs a title'),
            ));
        }
        return CoercionOutcome::ok(new self($title));
    }

    public function serialize(): mixed
    {
        return ['title' => $this->title];
    }
}
