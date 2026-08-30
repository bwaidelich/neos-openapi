<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation;

use Neos\OpenApi\ApiDefinition;
use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Compilation\ApiCompiler;
use Neos\OpenApi\Compilation\CompiledApi;
use Neos\OpenApi\Dispatch\ArgumentSource;
use Neos\OpenApi\Exception\InvalidApiDefinitionException;
use Neos\OpenApi\Response\ResponseHeader;
use Neos\OpenApi\Response\ResponseHeaders;
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Spec\SecurityRequirementObject;
use Neos\OpenApi\Spec\SecuritySchemeObject;
use Neos\OpenApi\Spec\SecuritySchemeOrReferenceObjectMap;
use Neos\OpenApi\Support\HttpMethod;
use Neos\OpenApi\Support\ParameterLocation;
use Neos\OpenApi\Support\RelativePath;
use Neos\OpenApi\Tests\Compilation\Fixtures\AnonymousAuthContextApi;
use Neos\OpenApi\Tests\Compilation\Fixtures\AuthorApi;
use Neos\OpenApi\Tests\Compilation\Fixtures\CollidingPathApi;
use Neos\OpenApi\Tests\Compilation\Fixtures\Invalid\MissingReturnTypeApi;
use Neos\OpenApi\Tests\Compilation\Fixtures\OptionalPathParameterApi;
use Neos\OpenApi\Tests\Compilation\Fixtures\PostApi;
use Neos\OpenApi\Tests\Compilation\Fixtures\ProxiedPostApi;
use Neos\OpenApi\Tests\Compilation\Fixtures\RawRequestApi;
use Neos\OpenApi\Tests\Compilation\Fixtures\ResponseHeaderApi;
use Neos\OpenApi\Tests\Compilation\Fixtures\TwoSuccessBranchesApi;
use Neos\OpenApi\Tests\Compilation\Fixtures\UnaccountedArgumentApi;
use Neos\OpenApi\Tests\Compilation\Fixtures\UnsecuredAuthContextApi;
use PHPUnit\Framework\TestCase;

/**
 * The compiler's own rules — what becomes a parameter, what becomes a response, and everything it refuses — over
 * fixtures that own real schemas. `tests/Schematic` covers the same ground end to end, over a whole small API.
 */
final class ApiCompilerTest extends TestCase
{
    private ApiCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new ApiCompiler();
    }

    private function definition(): ApiDefinition
    {
        return ApiDefinition::create(
            info: new InfoObject(title: 'Blog', version: '1.0.0'),
            securitySchemes: SecuritySchemeOrReferenceObjectMap::create()->with('bearerAuth', SecuritySchemeObject::bearer()),
        );
    }

    private function compilePostApi(): CompiledApi
    {
        return $this->compiler->compile($this->definition()->withOperationsFrom(PostApi::class, tag: 'Posts'));
    }

    /**
     * @return array<string, mixed>
     */
    private function document(CompiledApi $compiled): array
    {
        $decoded = json_decode((string) json_encode($compiled->document, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        /** @var array<string, mixed> $decoded */
        return $decoded;
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
            self::assertArrayHasKey($key, $current, sprintf('expected a "%s" member', $key));
            $next = $current[$key];
            self::assertIsArray($next, sprintf('expected "%s" to be an array', $key));
            $current = $next;
        }
        return $current;
    }

    /**
     * @param array<mixed> $data
     */
    private function stringAt(array $data, string|int ...$keys): string
    {
        $last = array_pop($keys);
        self::assertNotNull($last);
        $container = $this->arrayAt($data, ...$keys);
        self::assertArrayHasKey($last, $container);
        $value = $container[$last];
        self::assertIsString($value);
        return $value;
    }

    public function testOnlyMethodsCarryingTheOperationAttributeBecomeOperations(): void
    {
        $document = $this->document($this->compilePostApi());
        self::assertIsArray($document['paths']);

        self::assertSame(['/posts', '/posts/{slug}', '/posts/{slug}/views', '/health'], array_keys($document['paths']));
    }

    public function testTwoMethodsOnOnePathBecomeTwoMembersOfOnePathItem(): void
    {
        $document = $this->document($this->compilePostApi());
        self::assertIsArray($document['paths']);
        self::assertSame(['get', 'post'], array_keys($this->arrayAt($document, 'paths', '/posts')));
    }

    public function testAnArgumentNamedInThePathBecomesAPathParameter(): void
    {
        $entry = $this->compilePostApi()->dispatchTable->find(RelativePath::fromString('/posts/{slug}'), HttpMethod::GET);

        self::assertNotNull($entry);
        self::assertSame('getPost', $entry->methodName);
        self::assertCount(1, $entry->arguments);
        self::assertSame(ArgumentSource::path, $entry->arguments[0]->source);
        self::assertSame('slug', $entry->arguments[0]->wireName);
        self::assertTrue($entry->arguments[0]->required);
    }

    public function testEverythingElseBecomesAQueryParameter(): void
    {
        $entry = $this->compilePostApi()->dispatchTable->find(RelativePath::fromString('/posts'), HttpMethod::GET);

        self::assertNotNull($entry);
        $sources = array_map(static fn($a): string => $a->source->value, $entry->arguments);
        self::assertSame(['query', 'query'], $sources);
        // an argument with a default is not required
        self::assertFalse($entry->arguments[0]->required);
    }

    public function testTheParameterAttributeOverridesLocationAndWireName(): void
    {
        $entry = $this->compilePostApi()->dispatchTable->find(RelativePath::fromString('/posts/{slug}/views'), HttpMethod::GET);

        self::assertNotNull($entry);
        self::assertSame('clientId', $entry->arguments[1]->argumentName);
        self::assertSame(ArgumentSource::header, $entry->arguments[1]->source);
        self::assertSame('X-Client-Id', $entry->arguments[1]->wireName);
    }

    public function testTheParameterAttributeSurvivesIntoTheDocument(): void
    {
        $document = $this->document($this->compilePostApi());
        self::assertIsArray($document['paths']);
        $parameter = $this->arrayAt($document, 'paths', '/posts/{slug}/views', 'get', 'parameters', 1);

        self::assertSame('X-Client-Id', $parameter['name']);
        self::assertSame(ParameterLocation::header->value, $parameter['in']);
        self::assertSame('Who is asking', $parameter['description']);
    }

    public function testTheRequestBodyMustBeDeclaredAndIsNeverInferred(): void
    {
        $entry = $this->compilePostApi()->dispatchTable->find(RelativePath::fromString('/posts'), HttpMethod::POST);

        self::assertNotNull($entry);
        self::assertSame(ArgumentSource::body, $entry->arguments[0]->source);
        self::assertSame('post', $entry->arguments[0]->argumentName);
    }

    /**
     * The predecessor would have made this the request body positionally.
     */
    public function testAnUnaccountedArgumentOnAPostFailsLoudly(): void
    {
        $this->expectException(InvalidApiDefinitionException::class);
        $this->expectExceptionMessageMatches('/is not accounted for/');
        $this->compiler->compile($this->definition()->withOperationsFrom(UnaccountedArgumentApi::class));
    }

    public function testTheAuthContextArgumentIsDispatchedButNotPublished(): void
    {
        $compiled = $this->compilePostApi();
        $entry = $compiled->dispatchTable->find(RelativePath::fromString('/posts'), HttpMethod::POST);
        self::assertNotNull($entry);

        $sources = array_map(static fn($a): string => $a->source->value, $entry->arguments);
        self::assertSame(['body', 'authContext'], $sources);

        // ...but it is not a parameter of the published operation
        $document = $this->document($compiled);
        self::assertIsArray($document['paths']);
        $posts = $document['paths']['/posts'];
        self::assertIsArray($posts);
        $post = $posts['post'];
        self::assertIsArray($post);
        self::assertArrayNotHasKey('parameters', $post);
    }

    /**
     * A proxy generator re-declares the method it wraps and drops its parameters' attributes - PHP offers no way
     * to write them, so most generators do not. The declaration one level up still has them, and it is the one
     * that meant them; without this the body would become an ordinary parameter and the operation would fail to
     * compile, nowhere near the cause.
     */
    public function testParameterAttributesAreReadFromTheClassThatDeclaredThemNotFromAProxyOverIt(): void
    {
        $compiled = $this->compiler->compile($this->definition()->withOperationsFrom(ProxiedPostApi::class));
        $entry = $compiled->dispatchTable->find(RelativePath::fromString('/posts'), HttpMethod::POST);
        self::assertNotNull($entry);

        $sources = array_map(static fn($a): string => $a->source->value, $entry->arguments);
        self::assertSame(['body', 'authContext', 'header'], $sources);
        // the wire name off the #[Parameter] survived too, which the argument's own name could not have supplied
        self::assertSame('X-Trace-Id', $entry->arguments[2]->wireName);

        // ...and it is the proxy that gets invoked, not the class the attributes were read from
        self::assertSame(ProxiedPostApi::class, $entry->apiClassName);
    }

    /**
     * The request argument is decided by its type, and like the auth context it is dispatched without being
     * published - there is nothing about it for a client to send. The declared parameter next to it still is
     * published, so asking for the request does not turn the rest of the signature into an opaque blob.
     */
    public function testTheRequestArgumentIsDispatchedButNotPublished(): void
    {
        $compiled = $this->compiler->compile($this->definition()->withOperationsFrom(RawRequestApi::class));
        $entry = $compiled->dispatchTable->find(RelativePath::fromString('/search'), HttpMethod::GET);
        self::assertNotNull($entry);

        $sources = array_map(static fn($a): string => $a->source->value, $entry->arguments);
        self::assertSame(['request', 'query'], $sources);

        $document = $this->document($compiled);
        self::assertIsArray($document['paths']);
        $search = $document['paths']['/search'];
        self::assertIsArray($search);
        $get = $search['get'];
        self::assertIsArray($get);
        $parameters = $get['parameters'];
        self::assertIsArray($parameters);
        self::assertCount(1, $parameters);
        $only = $parameters[0];
        self::assertIsArray($only);
        self::assertSame('q', $only['name']);
    }

    public function testAnAuthContextOnAnUnsecuredOperationFailsLoudly(): void
    {
        $this->expectException(InvalidApiDefinitionException::class);
        $this->expectExceptionMessageMatches('/no caller to hand over/');
        $this->compiler->compile($this->definition()->withOperationsFrom(UnsecuredAuthContextApi::class));
    }

    /**
     * An operation that also allows anonymous access will hand over `null`, so the argument has to admit one —
     * the runtime would otherwise fail with a `TypeError` on exactly the requests it was told to allow.
     */
    public function testANonNullableAuthContextOnAnOperationAllowingAnonymousAccessFailsLoudly(): void
    {
        $this->expectException(InvalidApiDefinitionException::class);
        $this->expectExceptionMessageMatches('/has to be nullable/');
        $this->compiler->compile($this->definition()->withOperationsFrom(AnonymousAuthContextApi::class));
    }

    /**
     * A global requirement covers it just as an operation-level one does.
     */
    public function testAGlobalSecurityRequirementSatisfiesAnAuthContext(): void
    {
        $api = ApiDefinition::create(
            info: new InfoObject(title: 'Blog', version: '1.0.0'),
            security: SecurityRequirementObject::scheme('bearerAuth'),
        )->withOperationsFrom(UnsecuredAuthContextApi::class);

        $compiled = $this->compiler->compile($api);
        $entry = $compiled->dispatchTable->find(RelativePath::fromString('/posts'), HttpMethod::GET);

        self::assertNotNull($entry);
        self::assertSame(ArgumentSource::authContext, $entry->arguments[0]->source);
    }

    public function testDuplicateOperationIdsAcrossTwoApiClassesFailLoudly(): void
    {
        $api = $this->definition()
            ->withOperationsFrom(PostApi::class)
            ->withOperationsFrom(AuthorApi::class);

        $this->expectException(InvalidApiDefinitionException::class);
        $this->expectExceptionMessageMatches('/operationId "listPosts" is used by both/');
        $this->compiler->compile($api);
    }

    public function testTwoApiClassesClaimingOnePathAndMethodFailLoudly(): void
    {
        $api = $this->definition()
            ->withOperationsFrom(PostApi::class)
            ->withOperationsFrom(CollidingPathApi::class);

        $this->expectException(InvalidApiDefinitionException::class);
        $this->expectExceptionMessageMatches('/Two operations claim "GET \/posts"/');
        $this->compiler->compile($api);
    }

    public function testAMissingReturnTypeFailsLoudly(): void
    {
        $this->expectException(InvalidApiDefinitionException::class);
        $this->expectExceptionMessageMatches('/has no return type/');
        $this->compiler->compile($this->definition()->withOperationsFrom(MissingReturnTypeApi::class));
    }

    public function testAnOptionalPathParameterFailsLoudly(): void
    {
        $this->expectException(InvalidApiDefinitionException::class);
        $this->expectExceptionMessageMatches('/cannot be optional/');
        $this->compiler->compile($this->definition()->withOperationsFrom(OptionalPathParameterApi::class));
    }

    public function testEachApiClassGetsATagAndItsOperationsCarryIt(): void
    {
        $document = $this->document($this->compilePostApi());

        self::assertSame([['name' => 'Posts']], $document['tags']);
        self::assertSame(['Posts'], $this->arrayAt($document, 'paths', '/health', 'get', 'tags'));
    }

    public function testTheTagDefaultsToTheApiClassShortName(): void
    {
        $document = $this->document($this->compiler->compile($this->definition()->withOperationsFrom(AuthorApi::class)));

        self::assertSame([['name' => 'AuthorApi']], $document['tags']);
    }

    public function testRegisteringOneApiClassTwiceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->definition()->withOperationsFrom(PostApi::class)->withOperationsFrom(PostApi::class);
    }

    public function testAPlainReturnTypeBecomesA200(): void
    {
        $document = $this->document($this->compilePostApi());
        self::assertIsArray($document['paths']);
        self::assertSame('OK', $this->stringAt($document, 'paths', '/posts', 'get', 'responses', 200, 'description'));
        self::assertSame(
            ['$ref' => '#/components/schemas/Post'],
            $this->arrayAt($document, 'paths', '/posts', 'get', 'responses', 200, 'content', 'application/json', 'schema'),
        );
    }

    /**
     * A `void` operation is not undescribed: the handler answers it with a bodyless 204, so the document says 204.
     */
    public function testAVoidReturnTypeBecomesABodyless204(): void
    {
        $responses = $this->arrayAt($this->document($this->compilePostApi()), 'paths', '/health', 'get', 'responses');

        self::assertSame([204], array_keys($responses));
        self::assertSame('No Content', $this->stringAt($responses, 204, 'description'));
        self::assertArrayNotHasKey('content', $this->arrayAt($responses, 204));
    }

    public function testAUnionReturnTypeProducesOneResponsePerBranch(): void
    {
        $document = $this->document($this->compilePostApi());
        self::assertIsArray($document['paths']);
        $responses = $this->arrayAt($document, 'paths', '/posts/{slug}', 'get', 'responses');

        // ordered by status code (and keyed by integer, as PHP normalises numeric keys), not by discovery order
        self::assertSame([200, 400, 404], array_keys($responses));
        self::assertSame('No such post', $this->stringAt($responses, 404, 'description'));
        // NotFoundResponse declares no body type, so it documents no content
        self::assertArrayNotHasKey('content', $this->arrayAt($responses, 404));
    }

    /**
     * The gap the predecessor had: a non-200 response could carry a description but never document a body.
     */
    public function testANonSuccessResponseCanDocumentABody(): void
    {
        $document = $this->document($this->compilePostApi());
        self::assertIsArray($document['paths']);
        $responses = $this->arrayAt($document, 'paths', '/posts', 'post', 'responses');

        self::assertSame('That slug is taken', $this->stringAt($responses, 409, 'description'));
        self::assertSame(
            ['$ref' => '#/components/schemas/PostSlug'],
            $this->arrayAt($responses, 409, 'content', 'application/json', 'schema'),
        );
    }

    /**
     * A response declaring headers documents every one of them, with the schema of the type it declared — a value
     * object through the same component the body would use, a builtin inline.
     */
    public function testAResponseCanDocumentItsOwnHeaders(): void
    {
        $document = $this->document($this->compiler->compile($this->definition()->withOperationsFrom(ResponseHeaderApi::class)));
        self::assertIsArray($document['paths']);
        $headers = $this->arrayAt($document, 'paths', '/drafts', 'post', 'responses', 201, 'headers');

        self::assertSame(['X-Post-Slug', 'X-Rate-Limit-Remaining'], array_keys($headers));
        self::assertSame([
            'description' => 'The slug it got',
            'required' => true,
            'schema' => ['$ref' => '#/components/schemas/PostSlug'],
        ], $this->arrayAt($headers, 'X-Post-Slug'));
        // an optional header renders no `required` at all rather than `required: false`, as everywhere else
        self::assertArrayNotHasKey('required', $this->arrayAt($headers, 'X-Rate-Limit-Remaining'));
        self::assertArrayHasKey('schema', $this->arrayAt($headers, 'X-Rate-Limit-Remaining'));
    }

    public function testAResponseWithoutHeadersDocumentsNone(): void
    {
        $document = $this->document($this->compilePostApi());
        self::assertIsArray($document['paths']);
        self::assertArrayNotHasKey('headers', $this->arrayAt($document, 'paths', '/posts', 'post', 'responses', 409));
    }

    public function testAResponseMayNotDeclareAContentTypeHeader(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ResponseHeader::create('content-type', TypeReference::builtin(BuiltinType::string));
    }

    public function testTwoHeadersDifferingOnlyInCaseAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ResponseHeaders::create(
            ResponseHeader::create('ETag', TypeReference::builtin(BuiltinType::string)),
            ResponseHeader::create('etag', TypeReference::builtin(BuiltinType::string)),
        );
    }

    /**
     * An operation that takes input can reject it, so the document says so — with the schema of the payload the
     * runtime actually emits, not a bare description.
     */
    public function testAnOperationTakingInputAdvertisesAProblemDocumentFor400(): void
    {
        $document = $this->document($this->compilePostApi());
        self::assertIsArray($document['paths']);
        self::assertSame(
            ['$ref' => '#/components/schemas/ProblemDocument'],
            $this->arrayAt($document, 'paths', '/posts', 'get', 'responses', 400, 'content', 'application/problem+json', 'schema'),
        );
        self::assertArrayHasKey('ProblemDocument', $this->arrayAt($document, 'components', 'schemas'));
    }

    public function testAnOperationTakingNoInputGetsNo400(): void
    {
        $document = $this->document($this->compilePostApi());
        self::assertIsArray($document['paths']);
        self::assertArrayNotHasKey(400, $this->arrayAt($document, 'paths', '/health', 'get', 'responses'));
    }

    /**
     * The counterpart of the automatic 400: an operation the runtime will reject unauthenticated says so, with
     * the same payload the handler emits.
     */
    public function testASecuredOperationAdvertisesAProblemDocumentFor401(): void
    {
        $document = $this->document($this->compilePostApi());
        self::assertIsArray($document['paths']);
        self::assertSame(
            'The request was not authenticated',
            $this->stringAt($document, 'paths', '/posts', 'post', 'responses', 401, 'description'),
        );
        self::assertSame(
            ['$ref' => '#/components/schemas/ProblemDocument'],
            $this->arrayAt($document, 'paths', '/posts', 'post', 'responses', 401, 'content', 'application/problem+json', 'schema'),
        );
        // an operation with no security requirement has nothing to reject
        self::assertArrayNotHasKey(401, $this->arrayAt($document, 'paths', '/posts', 'get', 'responses'));
    }

    public function testTheOperationSecurityAndGlobalSchemesReachTheDocument(): void
    {
        $document = $this->document($this->compilePostApi());
        self::assertIsArray($document['paths']);
        self::assertSame([['bearerAuth' => []]], $this->arrayAt($document, 'paths', '/posts', 'post', 'security'));
        self::assertArrayHasKey('bearerAuth', $this->arrayAt($document, 'components', 'securitySchemes'));
    }

    public function testEverySchemaIsHoistedIntoComponents(): void
    {
        $document = $this->document($this->compilePostApi());
        $schemas = $this->arrayAt($document, 'components', 'schemas');

        // `Caller` is absent on purpose: it is the #[AuthContext] argument, which is never part of the request's
        // published shape, so nothing ever asks for its schema
        self::assertSame(['NewPost', 'Post', 'PostSlug', 'PostTitle', 'ProblemDocument'], array_keys($schemas));
    }

    /**
     * A type that cannot be constructed is refused rather than published: a body carrying one could never be read
     * back into it, so what a document said about it would only ever be half true.
     */
    public function testAReturnTypeThatCannotBeConstructedIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be instantiated/');
        $this->compiler->compile($this->definition()->withOperationsFrom(Fixtures\Invalid\AbstractReturnTypeApi::class));
    }

    /**
     * Several ordinary branches are one 200 describing several shapes: an `anyOf` over them, each branch hoisted
     * exactly the way a lone one would be — so a type published here is the same component it is anywhere else,
     * and everything it names in turn keeps its own component too.
     */
    public function testSeveralOrdinarySuccessBranchesBecomeAnAnyOfOverThem(): void
    {
        $compiled = $this->compiler->compile($this->definition()->withOperationsFrom(TwoSuccessBranchesApi::class));
        $document = $this->document($compiled);

        self::assertSame(
            ['anyOf' => [
                ['$ref' => '#/components/schemas/Post'],
                ['$ref' => '#/components/schemas/PostSlug'],
            ]],
            $this->arrayAt($document, 'paths', '/posts', 'get', 'responses', 200, 'content', 'application/json', 'schema'),
        );
        // `PostTitle` is named only *inside* `Post`: a branch is walked into like any other declared type
        self::assertSame(
            ['Post', 'PostSlug', 'PostTitle', 'ProblemDocument'],
            array_keys($this->arrayAt($document, 'components', 'schemas')),
        );

        $entry = $compiled->dispatchTable->find(RelativePath::fromString('/posts'), HttpMethod::GET);
        self::assertNotNull($entry);
        self::assertSame(
            [Fixtures\Post::class, Fixtures\PostSlug::class],
            array_map(static fn(TypeReference $type): string|null => $type->className(), $entry->successTypes),
        );
    }

    /**
     * Unions describe what an operation *answers*, never what it takes. Going out the value exists, so which
     * branch it is can simply be asked; coming in there is only primitives, and deciding which branch to build
     * them into is a question nothing here can answer.
     */
    public function testAUnionArgumentIsRejected(): void
    {
        $this->expectException(InvalidApiDefinitionException::class);
        $this->expectExceptionMessageMatches('/must have a single named type/');
        $this->compiler->compile($this->definition()->withOperationsFrom(Fixtures\Invalid\UnionArgumentApi::class));
    }

    /**
     * Branches are allowed to overlap — a `DetailedReply` is a `Reply` — because nothing has to tell them apart:
     * the handler renders a result through the first branch it matches, and `anyOf` asks a consumer for no more
     * than that either.
     */
    public function testBranchesThatOverlapAreAccepted(): void
    {
        $document = $this->document($this->compiler->compile(
            $this->definition()->withOperationsFrom(Fixtures\OverlappingBranchesApi::class),
        ));

        self::assertSame(
            ['anyOf' => [
                ['$ref' => '#/components/schemas/Reply'],
                ['$ref' => '#/components/schemas/DetailedReply'],
            ]],
            $this->arrayAt($document, 'paths', '/replies', 'get', 'responses', 200, 'content', 'application/json', 'schema'),
        );
    }

    /**
     * The runtime serializes through the *declared* success type, not the returned value's own class — only the
     * declared one is what the document promises.
     */
    public function testTheEntryCarriesTheDeclaredSuccessType(): void
    {
        $table = $this->compilePostApi()->dispatchTable;

        $getPost = $table->find(RelativePath::fromString('/posts/{slug}'), HttpMethod::GET);
        self::assertNotNull($getPost);
        self::assertCount(1, $getPost->successTypes);
        self::assertSame(Fixtures\Post::class, $getPost->successTypes[0]->className());

        // `void` declares no success body at all
        $health = $table->find(RelativePath::fromString('/health'), HttpMethod::GET);
        self::assertNotNull($health);
        self::assertSame([], $health->successTypes);
    }

    public function testTheDispatchTableIsSerializable(): void
    {
        $table = $this->compilePostApi()->dispatchTable;

        $restored = unserialize(serialize($table));
        self::assertInstanceOf(\Neos\OpenApi\Dispatch\DispatchTable::class, $restored);
        $entry = $restored->find(RelativePath::fromString('/posts/{slug}'), HttpMethod::GET);
        self::assertNotNull($entry);
        self::assertSame('getPost', $entry->methodName);
    }

    /**
     * Both halves come out of one pass, so every dispatchable operation is also a published one.
     */
    public function testTheDocumentAndTheDispatchTableDescribeTheSameOperations(): void
    {
        $compiled = $this->compilePostApi();

        $published = [];
        foreach ($compiled->document->paths ?? [] as $path => $pathObject) {
            foreach ($pathObject->operations() as $member => $operation) {
                $published[] = strtoupper($member) . ' ' . $path;
            }
        }
        $dispatchable = array_keys(iterator_to_array($compiled->dispatchTable->all()));

        sort($published);
        sort($dispatchable);
        self::assertSame($published, $dispatchable);
    }
}
