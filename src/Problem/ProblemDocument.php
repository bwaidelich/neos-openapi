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
     * The status codes RFC 9110 defines, each of which has a section with a stable `status.<code>` anchor there.
     */
    private const RFC9110_STATUS_CODES = [
        100, 101,
        200, 201, 202, 203, 204, 205, 206,
        300, 301, 302, 303, 304, 305, 306, 307, 308,
        400, 401, 402, 403, 404, 405, 406, 407, 408, 409, 410, 411, 412, 413, 414, 415, 416, 417, 418, 421, 422, 426,
        500, 501, 502, 503, 504, 505,
    ];

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
        return new self(self::typeUriFor($status), $title, $status, $detail, $mapped);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString(self::CONTENT_TYPE);
    }

    /**
     * Deliberately open: RFC 9457 allows extension members, so `additionalProperties` stays unset rather than
     * `false`. Nothing builds a ProblemDocument *from* this schema — it is what the document advertises and what
     * the tests hold the emitted payload to — so the openness costs nothing.
     */
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

    /**
     * The type names the status code's definition, never the title: a title is free text ("Book not found"), and
     * RFC 9110 only has anchors for its own sections. Those are addressed by their explicit `status.<code>` anchor
     * rather than the heading-derived `name-…` one, which xml2rfc truncates (`name-407-proxy-authentication-re`).
     * A status code RFC 9110 does not define gets `about:blank`, which RFC 9457 reserves for "nothing beyond what
     * the status code says".
     */
    private static function typeUriFor(HttpStatusCode $status): string
    {
        if (!in_array($status->value, self::RFC9110_STATUS_CODES, true)) {
            return 'about:blank';
        }
        return sprintf('https://www.rfc-editor.org/rfc/rfc9110#status.%d', $status->value);
    }
}
