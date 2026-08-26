<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\JsonSchema\Validation\Issue;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\JsonSchema\Validation\Issues;
use Neos\OpenApi\Binding\CoercionOutcome;

final readonly class TodoId implements Coercible
{
    private function __construct(
        public string $value,
    ) {}

    public static function create(string $value): self
    {
        return new self($value);
    }

    public static function coerce(mixed $input): CoercionOutcome
    {
        if (!is_string($input) || !preg_match('/^[a-z0-9-]+$/', $input)) {
            return CoercionOutcome::failed(Issues::create(
                Issue::create([], IssueCode::InvalidPattern, 'A todo id consists of lowercase letters, digits and dashes'),
            ));
        }
        return CoercionOutcome::ok(new self($input));
    }

    public function serialize(): string
    {
        return $this->value;
    }
}
