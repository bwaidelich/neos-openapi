<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Neos\OpenApi\ApiDefinition;
use Neos\OpenApi\Compilation\ApiCompiler;
use Neos\OpenApi\Http\RequestHandler;
use Neos\OpenApi\Problem\ProblemDocument;
use Neos\OpenApi\Schematic\SchematicTypeBindingProvider;
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Support\FixedContainer;
use Neos\OpenApi\Tests\Schematic\Fixtures\BlogApi;
use Neos\Schematic\Attributes\ReflectionMiddleware;
use Neos\Schematic\Schematic;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * End to end over the real engine: a request in, a domain object out, and back through the very schema the
 * document published for it.
 *
 * `tests/Http/RequestHandlerTest.php` covers the handler's own behaviour against the port with no engine at all —
 * which is what proves core needs none. This is the other half: that the two actually fit together, and that a
 * value object, an enum and a discriminated union survive the round trip.
 */
final class RequestHandlingTest extends TestCase
{
    private BlogApi $api;
    private RequestHandler $handler;

    protected function setUp(): void
    {
        $this->api = new BlogApi();
        $provider = new SchematicTypeBindingProvider(Schematic::create(new ReflectionMiddleware()));
        $compiled = (new ApiCompiler($provider))->compile(
            ApiDefinition::create(info: new InfoObject(title: 'Blog', version: '1.0.0'))->withOperationsFrom(BlogApi::class),
        );
        $factory = new HttpFactory();
        $this->handler = new RequestHandler($compiled, $provider, new FixedContainer($this->api), $factory, $factory);
    }

    private function handle(string $method, string $uri, string|null $body = null): ResponseInterface
    {
        $request = new ServerRequest($method, $uri, [], $body);
        parse_str($request->getUri()->getQuery(), $query);
        return $this->handler->handle($request->withQueryParams($query));
    }

    /**
     * @return array<mixed>
     */
    private function decoded(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        return $decoded;
    }

    public function testAValueObjectIsBuiltFromThePathAndTheResultSerializedByItsSchema(): void
    {
        $response = $this->handle('GET', '/authors/Ada%20Lovelace');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(['name' => 'Ada Lovelace', 'posts' => 0, 'pseudonym' => null], $this->decoded($response));
    }

    public function testAnEnumQueryParameterArrivesAsItsCase(): void
    {
        self::assertSame(['draft'], $this->decoded($this->handle('GET', '/authors?status=draft')));
        // absent and optional, so the method's own default applies
        self::assertSame(['everyone'], $this->decoded($this->handle('GET', '/authors')));
    }

    public function testAPolymorphicBodySurvivesTheRoundTripWithItsTag(): void
    {
        $response = $this->handle('POST', '/blocks', '{"kind":"text","body":"Hello"}');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['kind' => 'text', 'body' => 'Hello'], $this->decoded($response));
    }

    /**
     * The other half of the header the document describes: the value goes out through the very binding that
     * schema came from, so a `AuthorName` reaches the wire as the string its schema says it is.
     */
    public function testAResponseHeaderIsWrittenThroughTheBindingThatDescribedIt(): void
    {
        $response = $this->handle('POST', '/authors', '"Ada Lovelace"');

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Ada Lovelace', $response->getHeaderLine('X-Author-Name'));
    }

    /**
     * The engine's own issues, located within the request they came from.
     */
    public function testAValueTheSchemaRejectsBecomesA400NamingIt(): void
    {
        $response = $this->handle('GET', '/authors/' . rawurlencode(str_repeat('x', 201)));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        $issues = $this->decoded($response)['issues'];
        self::assertIsArray($issues);
        self::assertSame(['/path/name'], array_column($issues, 'pointer'));
        self::assertSame(['too_long'], array_column($issues, 'code'));
    }

    public function testAnUnknownDiscriminatorTagIsReportedAtTheTagsOwnPath(): void
    {
        $response = $this->handle('POST', '/blocks', '{"kind":"video","body":"Hello"}');
        $issues = $this->decoded($response)['issues'];

        self::assertSame(400, $response->getStatusCode());
        self::assertIsArray($issues);
        self::assertSame(['/body/kind'], array_column($issues, 'pointer'));
    }

    /**
     * The document promises `application/problem+json` for a 400, and this is the payload that arrives — the same
     * class, through the same schema.
     */
    public function testTheRejectionValidatesAgainstTheSchemaTheDocumentAdvertises(): void
    {
        $response = $this->handle('POST', '/blocks', '{"kind":"video"}');

        $result = ProblemDocument::schema()->validate($this->decoded($response));

        self::assertSame(400, $response->getStatusCode());
        self::assertTrue($result->valid, (string) $result->issues);
    }
}
