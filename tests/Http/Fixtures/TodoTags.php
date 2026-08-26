<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\JsonSchema\Validation\Issue;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\JsonSchema\Validation\Issues;
use Neos\OpenApi\Binding\CoercionOutcome;

/**
 * Serializes to a list of strings — which as a header value is the same header sent once per element.
 */
final readonly class TodoTags implements Coercible
{
    /**
     * @var list<string>
     */
    private array $tags;

    private function __construct(string ...$tags)
    {
        $this->tags = array_values($tags);
    }

    public static function of(string ...$tags): self
    {
        return new self(...$tags);
    }

    public static function coerce(mixed $input): CoercionOutcome
    {
        if (!is_array($input)) {
            return CoercionOutcome::failed(Issues::create(Issue::create([], IssueCode::InvalidType, 'Not a list of tags')));
        }
        $tags = [];
        foreach ($input as $tag) {
            if (!is_string($tag)) {
                return CoercionOutcome::failed(Issues::create(Issue::create([], IssueCode::InvalidType, 'Not a tag')));
            }
            $tags[] = $tag;
        }
        return CoercionOutcome::ok(new self(...$tags));
    }

    /**
     * @return list<string>
     */
    public function serialize(): array
    {
        return $this->tags;
    }
}
