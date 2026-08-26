<?php

declare(strict_types=1);

namespace Neos\OpenApi\Problem;

use JsonSerializable;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema as JsonSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;
use Neos\JsonSchema\Validation\Issues;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * An [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) error payload, plus an `issues` extension member listing
 * every value that was rejected.
 *
 * It describes itself via {@see ProvidesSchema}, so the schema a document advertises for an error response and the
 * payload the runtime emits come from the same class — which is the guarantee that matters. (It cannot be
 * described through the TypeBinding port instead: doing so would mean annotating this class with attributes from
 * the schema engine, and core may not name it. `ProvidesSchema` is a `neos/jsonschema` contract, which core
 * depends on anyway.) `tests/ProblemDocumentTest.php` pins that an emitted document validates against it.
 */
final readonly class ProblemDocument implements JsonSerializable, ProvidesSchema
{
    public const CONTENT_TYPE = 'application/problem+json';

    /**
     * @param list<ProblemIssue> $issues
     */
    private function __construct(
        public string $type,
        public string $title,
        public HttpStatusCode $status,
        public string|null $detail,
        public array $issues,
    ) {}

    public static function create(
        HttpStatusCode $status,
        string $title,
        string|null $detail = null,
        Issues|null $issues = null,
    ): self {
        $mapped = [];
        foreach ($issues?->toArray() ?? [] as $issue) {
            $mapped[] = ProblemIssue::fromIssue($issue);
        }
        return new self(self::typeUriFor($status, $title), $title, $status, $detail, $mapped);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString(self::CONTENT_TYPE);
    }

    public static function schema(): JsonSchema
    {
        return ObjectSchema::create(
            title: 'ProblemDocument',
            description: 'An RFC 9457 problem details document',
            properties: ObjectProperties::create(
                type: StringSchema::create(description: 'A URI identifying the problem type'),
                title: StringSchema::create(description: 'A short, human-readable summary of the problem type'),
                status: IntegerSchema::create(description: 'The HTTP status code', minimum: 100, maximum: 599),
                detail: StringSchema::create(description: 'A human-readable explanation specific to this occurrence'),
                issues: ProblemIssue::listSchema(),
            ),
            required: ['type', 'title', 'status'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $document = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status->value,
        ];
        if ($this->detail !== null && $this->detail !== '') {
            $document['detail'] = $this->detail;
        }
        if ($this->issues !== []) {
            $document['issues'] = $this->issues;
        }
        return $document;
    }

    private static function typeUriFor(HttpStatusCode $status, string $title): string
    {
        return sprintf(
            'https://www.rfc-editor.org/rfc/rfc9110#name-%d-%s',
            $status->value,
            strtolower(str_replace(' ', '-', $title)),
        );
    }
}
