<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Problem;

use Neos\JsonSchema\Validation\Issue;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\JsonSchema\Validation\Issues;
use Neos\OpenApi\Problem\ProblemDocument;
use Neos\OpenApi\Support\HttpStatusCode;
use PHPUnit\Framework\TestCase;

/**
 * The guarantee that matters for an error response: the schema a document advertises for it and the payload the
 * runtime emits come from the same class, so they cannot drift apart.
 */
final class ProblemDocumentTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function encode(ProblemDocument $document): array
    {
        $decoded = json_decode((string) json_encode($document, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public function testAnEmittedDocumentValidatesAgainstItsOwnAdvertisedSchema(): void
    {
        $document = ProblemDocument::create(
            status: HttpStatusCode::fromInteger(400),
            title: 'Bad Request',
            detail: 'The request body could not be understood',
            issues: Issues::create(
                Issue::create(['name'], IssueCode::TooShort, 'Value must be at least 1 character(s) long'),
                Issue::create(['posts', 0], IssueCode::InvalidType, 'Expected an integer, got string'),
            ),
        );

        $result = ProblemDocument::schema()->validate($this->encode($document));

        self::assertTrue($result->valid, (string) ($result->issues->toArray()[0] ?? ''));
    }

    public function testAMinimalDocumentAlsoValidates(): void
    {
        $document = ProblemDocument::create(HttpStatusCode::fromInteger(404), 'Not Found');

        self::assertTrue(ProblemDocument::schema()->validate($this->encode($document))->valid);
        self::assertSame(['type', 'title', 'status'], array_keys($this->encode($document)));
    }

    public function testTheTypeUriNamesTheStatusAndTitle(): void
    {
        $document = ProblemDocument::create(HttpStatusCode::fromInteger(400), 'Bad Request');

        self::assertSame('https://www.rfc-editor.org/rfc/rfc9110#name-400-bad-request', $document->type);
    }

    /**
     * `Neos\JsonSchema\Validation\Issue` is another package's internal type; what goes on the wire is this
     * package's own documented shape, with the path as an RFC 6901 pointer.
     */
    public function testIssuesAreMappedToADocumentedShapeRatherThanSerializedDirectly(): void
    {
        $document = ProblemDocument::create(
            HttpStatusCode::fromInteger(400),
            'Bad Request',
            issues: Issues::create(Issue::create(['posts', 0, 'title'], IssueCode::TooShort, 'too short')),
        );

        $encoded = $this->encode($document);
        self::assertSame(
            [['code' => 'too_short', 'message' => 'too short', 'pointer' => '/posts/0/title']],
            $encoded['issues'],
        );
    }

    public function testAPointerEscapesTheCharactersRfc6901Reserves(): void
    {
        $document = ProblemDocument::create(
            HttpStatusCode::fromInteger(400),
            'Bad Request',
            issues: Issues::create(Issue::create(['a/b', 'c~d'], IssueCode::InvalidType, 'nope')),
        );

        $encoded = $this->encode($document);
        self::assertIsArray($encoded['issues']);
        self::assertSame('/a~1b/c~0d', $this->pointerOf($encoded['issues']));
    }

    /**
     * @param array<mixed> $issues
     */
    private function pointerOf(array $issues): string
    {
        $first = $issues[0];
        self::assertIsArray($first);
        $pointer = $first['pointer'];
        self::assertIsString($pointer);
        return $pointer;
    }

    public function testTheContentTypeIsTheRfc9457One(): void
    {
        self::assertSame('application/problem+json', ProblemDocument::contentType()->value);
    }
}
