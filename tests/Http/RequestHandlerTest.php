<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Neos\OpenApi\ApiDefinition;
use Neos\OpenApi\Compilation\ApiCompiler;
use Neos\OpenApi\Compilation\CompiledApi;
use Neos\OpenApi\Http\AuthContextProvider;
use Neos\OpenApi\Http\RequestHandler;
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Spec\SecurityRequirementObject;
use Neos\OpenApi\Spec\SecuritySchemeObject;
use Neos\OpenApi\Spec\SecuritySchemeOrReferenceObjectMap;
use Neos\OpenApi\Support\FixedContainer;
use Neos\OpenApi\Tests\Http\Fixtures\Broken\BrokenHeaderApi;
use Neos\OpenApi\Tests\Http\Fixtures\Caller;
use Neos\OpenApi\Tests\Http\Fixtures\NewTodo;
use Neos\OpenApi\Tests\Http\Fixtures\TodoApi;
use Neos\OpenApi\Tests\Http\Fixtures\TodoId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The runtime, driven against the port with no schema engine behind it — which is what the architecture test
 * enforces. `tests/Schematic/RequestHandlingTest.php` covers the same ground with the real one.
 */
final class RequestHandlerTest extends TestCase
{
    private TodoApi $api;
    private CompiledApi $compiled;
    private Caller|null $caller = null;

    protected function setUp(): void
    {
        $this->api = new TodoApi();
        $this->compiled = $this->compile('Todos');
    }

    /**
     * The title is a parameter because a Basic challenge's realm comes from it.
     */
    private function compile(string $title): CompiledApi
    {
        return (new ApiCompiler(new FixtureTypeBindingProvider()))->compile(
            ApiDefinition::create(
                info: new InfoObject(title: $title, version: '1.0.0'),
                securitySchemes: SecuritySchemeOrReferenceObjectMap::create()
                    ->with('bearerAuth', SecuritySchemeObject::bearer())
                    ->with('basicAuth', SecuritySchemeObject::basic()),
            )->withOperationsFrom(TodoApi::class),
        );
    }

    private function handler(): RequestHandler
    {
        $factory = new HttpFactory();
        $authContexts = new class ($this->caller) implements AuthContextProvider {
            public function __construct(private readonly Caller|null $caller) {}

            public function authContextFor(ServerRequestInterface $request, SecurityRequirementObject $requirement): object|null
            {
                return $request->hasHeader('Authorization') ? $this->caller : null;
            }
        };
        return new RequestHandler(
            $this->compiled,
            new FixtureTypeBindingProvider(),
            new FixedContainer($this->api),
            $factory,
            $factory,
            $authContexts,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function handle(string $method, string $uri, array $headers = [], string|null $body = null): ResponseInterface
    {
        $request = new ServerRequest($method, $uri, $headers, $body);
        parse_str($request->getUri()->getQuery(), $query);
        return $this->handler()->handle($request->withQueryParams($query));
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

    /**
     * @return list<array<mixed>> the `issues` member of a Problem Document
     */
    private function issues(ResponseInterface $response): array
    {
        $document = $this->decoded($response);
        self::assertArrayHasKey('issues', $document);
        self::assertIsArray($document['issues']);
        $issues = [];
        foreach ($document['issues'] as $issue) {
            self::assertIsArray($issue);
            $issues[] = $issue;
        }
        return $issues;
    }

    /**
     * Walks into a decoded document, asserting each hop exists and is an array.
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private function arrayAt(array $data, string|int ...$keys): array
    {
        $current = $data;
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $current);
            $next = $current[$key];
            self::assertIsArray($next);
            $current = $next;
        }
        return $current;
    }

    public function testAPathParameterReachesTheMethodAsItsType(): void
    {
        $response = $this->handle('GET', '/todos/write-tests');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertEquals(TodoId::create('write-tests'), $this->api->lastArguments['id']);
        self::assertSame(['id' => 'write-tests', 'title' => 'Write the handler', 'done' => false], $this->decoded($response));
    }

    /**
     * Two operations one path segment apart: the collection and one of its items must not be confused.
     */
    public function testTheCollectionPathAndTheItemTemplateAreToldApart(): void
    {
        self::assertCount(2, $this->decoded($this->handle('GET', '/todos')));
        self::assertArrayHasKey('title', $this->decoded($this->handle('GET', '/todos/one')));
    }

    public function testAnAbsentOptionalParameterLetsTheMethodsOwnDefaultApply(): void
    {
        $this->handle('GET', '/todos');

        self::assertSame(['limit' => 2, 'client' => null], $this->api->lastArguments);
    }

    public function testAQueryParameterIsCoercedOutOfItsStringForm(): void
    {
        $this->handle('GET', '/todos?limit=3');

        self::assertSame(3, $this->api->lastArguments['limit']);
    }

    public function testAHeaderParameterIsReadUnderItsWireNameNotTheArgumentsOwn(): void
    {
        $this->handle('GET', '/todos', ['X-Client-Id' => 'cli']);

        self::assertSame('cli', $this->api->lastArguments['client']);
    }

    public function testAnUnknownPathIsAProblemDocument(): void
    {
        $response = $this->handle('GET', '/nope');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame(404, $this->decoded($response)['status']);
    }

    public function testAKnownPathWithAnUnansweredMethodIsA405ListingTheOnesItAnswers(): void
    {
        $response = $this->handle('PATCH', '/todos');

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET, POST', $response->getHeaderLine('Allow'));
        self::assertSame('Method Not Allowed', $this->decoded($response)['title']);
    }

    public function testARejectedParameterIsReportedAtItsOwnLocation(): void
    {
        $response = $this->handle('GET', '/todos/NOT VALID');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            [['code' => 'invalid_pattern', 'message' => 'A todo id consists of lowercase letters, digits and dashes', 'pointer' => '/path/id']],
            $this->issues($response),
        );
    }

    public function testAMissingRequiredParameterIsReportedRatherThanGuessed(): void
    {
        $response = $this->handle('GET', '/reports?limit=1');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            [['code' => 'required', 'message' => 'The query parameter "id" is required', 'pointer' => '/query/id']],
            $this->issues($response),
        );
    }

    /**
     * Every reason at once — a value that was rejected and one that was never sent — because fixing two mistakes
     * should take one round trip, not two.
     */
    public function testEveryRejectedValueIsReportedTogether(): void
    {
        $issues = $this->issues($this->handle('GET', '/reports?id=NOT%20VALID'));

        self::assertSame(['/query/id', '/query/limit'], array_column($issues, 'pointer'));
        self::assertSame(['invalid_pattern', 'required'], array_column($issues, 'code'));
    }

    public function testARequestBodyIsCoercedAndTheCallerHandedOver(): void
    {
        $this->caller = Caller::named('ada');
        $response = $this->handle('POST', '/todos', ['Authorization' => 'Bearer t'], '{"title":"Ship it"}');

        self::assertSame(201, $response->getStatusCode());
        self::assertEquals(NewTodo::coerce(['title' => 'Ship it'])->value(), $this->api->lastArguments['todo']);
        self::assertSame($this->caller, $this->api->lastArguments['caller']);
        self::assertSame(['id' => 'new', 'title' => 'Ship it', 'done' => false], $this->decoded($response));
    }

    public function testAnIssueInsideTheBodyKeepsItsPathBelowIt(): void
    {
        $this->caller = Caller::named('ada');
        $response = $this->handle('POST', '/todos', ['Authorization' => 'Bearer t'], '{"title":""}');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['/body/title'], array_column($this->issues($response), 'pointer'));
    }

    public function testAMissingRequiredBodyIsReported(): void
    {
        $this->caller = Caller::named('ada');
        $response = $this->handle('POST', '/todos', ['Authorization' => 'Bearer t']);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['/body'], array_column($this->issues($response), 'pointer'));
    }

    public function testAMalformedBodyIsRejectedWithoutBlamingAValue(): void
    {
        $this->caller = Caller::named('ada');
        $response = $this->handle('POST', '/todos', ['Authorization' => 'Bearer t'], '{not json');
        $document = $this->decoded($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertArrayNotHasKey('issues', $document);
        self::assertIsString($document['detail']);
        self::assertStringContainsString('not valid JSON', $document['detail']);
    }

    public function testANonJsonBodyIsRejected(): void
    {
        $this->caller = Caller::named('ada');
        $response = $this->handle('POST', '/todos', ['Authorization' => 'Bearer t', 'Content-Type' => 'text/plain'], 'Ship it');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Only JSON request bodies are supported, got "text/plain"', $this->decoded($response)['detail']);
    }

    public function testAnUnauthenticatedRequestToASecuredOperationIsChallenged(): void
    {
        $response = $this->handle('POST', '/todos', [], '{"title":"Ship it"}');

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame([], $this->api->lastArguments, 'the operation must not have run');
    }

    /**
     * A Basic scheme is challenged as `Basic`, with the realm RFC 7617 wants alongside it — which the Security
     * Scheme Object has nowhere to carry, so it comes from the document's own title.
     */
    public function testABasicSchemeIsChallengedWithARealm(): void
    {
        $response = $this->handle('POST', '/todos/archive');

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Basic realm="Todos"', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame([], $this->api->lastArguments, 'the operation must not have run');
    }

    public function testARealmIsEscapedForTheQuotedStringItSitsIn(): void
    {
        $this->compiled = $this->compile('The "Todo" API \\ friends');

        self::assertSame(
            'Basic realm="The \\"Todo\\" API \\\\ friends"',
            $this->handle('POST', '/todos/archive')->getHeaderLine('WWW-Authenticate'),
        );
    }

    public function testCredentialsForABasicSchemeReachTheOperation(): void
    {
        $this->caller = Caller::named('ada');
        $response = $this->handle('POST', '/todos/archive', ['Authorization' => 'Basic YWRhOnNlY3JldA==']);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(['archived' => true], $this->api->lastArguments);
    }

    /**
     * The 401 the handler emits is the 401 the document advertises — the same argument the automatic 400 makes.
     */
    public function testTheChallengedStatusIsOneTheDocumentDescribes(): void
    {
        $document = json_decode((string) json_encode($this->compiled->document, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($document);

        self::assertArrayHasKey(401, $this->arrayAt($document, 'paths', '/todos', 'post', 'responses'));
    }

    public function testAnApiResponseBranchFixesItsOwnStatusAndBody(): void
    {
        $response = $this->handle('GET', '/todos/missing');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertFalse($response->hasHeader('Content-Type'));
    }

    public function testAVoidOperationAnswersABodyless204(): void
    {
        $response = $this->handle('DELETE', '/todos/write-tests');

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertEquals(TodoId::create('write-tests'), $this->api->lastArguments['id']);
    }

    public function testTheApiClassIsResolvedAtRequestTime(): void
    {
        $resolved = 0;
        $factory = new HttpFactory();
        $resolver = new class ($this->api, $resolved) implements \Psr\Container\ContainerInterface {
            public function __construct(private readonly TodoApi $api, public int &$resolved) {}

            public function get(string $id): object
            {
                $this->resolved++;
                return $this->api;
            }

            public function has(string $id): bool
            {
                return $id === TodoApi::class;
            }
        };
        $handler = new RequestHandler($this->compiled, new FixtureTypeBindingProvider(), $resolver, $factory, $factory);

        $handler->handle(new ServerRequest('GET', '/todos/one'));
        $handler->handle(new ServerRequest('GET', '/todos/two'));

        self::assertSame(2, $resolved);
    }

    /**
     * The other half of a declared header: what the document advertises for a `201` is what the wire carries,
     * each value written through the binding its declared type names.
     */
    public function testAResponseWritesTheHeadersItDeclared(): void
    {
        $this->caller = Caller::named('ada');
        $response = $this->handle('POST', '/todos', ['Authorization' => 'Bearer t'], '{"title":"Ship it"}');

        self::assertSame('/todos/new', $response->getHeaderLine('Location'));
        self::assertSame('41', $response->getHeaderLine('X-Rate-Limit-Remaining'));
        // a value that serializes to a list is the same header once per element, which is what a repeated
        // header is — never one comma-joined string invented here
        self::assertSame(['fresh', 'unsorted'], $response->getHeader('X-Todo-Tags'));
        // the declared headers do not disturb the body, which still goes out through its own binding
        self::assertSame(['id' => 'new', 'title' => 'Ship it', 'done' => false], $this->decoded($response));
    }

    public function testAnOptionalHeaderWithNoValueIsNotSent(): void
    {
        $this->caller = Caller::named('ada');
        $response = $this->handle('POST', '/todos', ['Authorization' => 'Bearer t'], '{"title":"Quietly"}');

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('/todos/new', $response->getHeaderLine('Location'));
        self::assertFalse($response->hasHeader('X-Rate-Limit-Remaining'));
        // an empty list is a repeated header repeated no times, which is the same as not sending it
        self::assertFalse($response->hasHeader('X-Todo-Tags'));
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function brokenHeaderProvider(): array
    {
        return [
            'a header the response never declared' => ['/undeclared', 1783500415],
            'a required header with no value' => ['/missing', 1783500416],
            'a value no header field can carry' => ['/unrenderable', 1783500417],
        ];
    }

    /**
     * A response contradicting its own declaration is a bug in the API, not in the request — so it raises rather
     * than reaching the caller as an undocumented header, a missing one, or a mangled one.
     */
    #[DataProvider('brokenHeaderProvider')]
    public function testAResponseContradictingItsOwnHeaderDeclarationFailsLoudly(string $path, int $code): void
    {
        $factory = new HttpFactory();
        $api = new BrokenHeaderApi();
        $compiled = (new ApiCompiler(new FixtureTypeBindingProvider()))->compile(
            ApiDefinition::create(info: new InfoObject(title: 'Broken', version: '1.0.0'))->withOperationsFrom(BrokenHeaderApi::class),
        );
        $handler = new RequestHandler($compiled, new FixtureTypeBindingProvider(), new FixedContainer($api), $factory, $factory);

        $this->expectException(\LogicException::class);
        $this->expectExceptionCode($code);
        $handler->handle(new ServerRequest('GET', $path));
    }

    public function testASecuredOperationWithNoAuthContextProviderIsAProgrammingError(): void
    {
        $factory = new HttpFactory();
        $handler = new RequestHandler(
            $this->compiled,
            new FixtureTypeBindingProvider(),
            new FixedContainer($this->api),
            $factory,
            $factory,
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionCode(1783500412);
        $handler->handle(new ServerRequest('POST', '/todos', [], '{"title":"Ship it"}'));
    }
}
