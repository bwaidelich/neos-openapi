<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\JsonSchema\Validation\Issue;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\JsonSchema\Validation\Issues;
use Neos\OpenApi\Binding\CoercionOutcome;

/**
 * The API's own notion of who is calling — handed over by an {@see \Neos\OpenApi\Http\AuthContextProvider}, never
 * coerced from the request, which is why its `coerce()` refuses outright.
 */
final readonly class Caller implements Coercible
{
    private function __construct(
        public string $name,
    ) {}

    public static function named(string $name): self
    {
        return new self($name);
    }

    public static function coerce(mixed $input): CoercionOutcome
    {
        return CoercionOutcome::failed(Issues::create(
            Issue::create([], IssueCode::InvalidType, 'A caller never comes from the request'),
        ));
    }

    public function serialize(): mixed
    {
        return $this->name;
    }
}
