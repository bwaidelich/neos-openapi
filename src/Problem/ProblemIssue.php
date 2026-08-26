<?php

declare(strict_types=1);

namespace Neos\OpenApi\Problem;

use JsonSerializable;
use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema as JsonSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;
use Neos\JsonSchema\Validation\Issue;

/**
 * One rejected value, as it appears on the wire.
 *
 * A shape of this package's own rather than `Neos\JsonSchema\Validation\Issue` serialized directly: that type's
 * serialization is another package's internal concern, and putting it on the wire would make it this package's
 * public contract.
 */
final readonly class ProblemIssue implements JsonSerializable, ProvidesSchema
{
    /**
     * @param list<string|int> $path
     */
    private function __construct(
        public string $code,
        public string $message,
        public array $path,
    ) {}

    public static function fromIssue(Issue $issue): self
    {
        return new self($issue->code, $issue->message, $issue->path);
    }

    public static function schema(): JsonSchema
    {
        return ObjectSchema::create(
            description: 'One rejected value',
            properties: ObjectProperties::create(
                code: StringSchema::create(description: 'A machine-readable reason'),
                message: StringSchema::create(description: 'A human-readable reason'),
                pointer: StringSchema::create(description: 'An RFC 6901 JSON Pointer to the offending value'),
            ),
            additionalProperties: false,
            required: ['code', 'message', 'pointer'],
        );
    }

    public static function listSchema(): ArraySchema
    {
        return ArraySchema::create(description: 'Every value that was rejected', items: self::schema());
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'pointer' => $this->pointer(),
        ];
    }

    /**
     * The path as an RFC 6901 JSON Pointer, which is the form RFC 9457 error documents use.
     */
    public function pointer(): string
    {
        $pointer = '';
        foreach ($this->path as $segment) {
            $pointer .= '/' . str_replace(['~', '/'], ['~0', '~1'], (string) $segment);
        }
        return $pointer;
    }
}
