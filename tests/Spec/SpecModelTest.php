<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Spec;

use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;
use Neos\OpenApi\Exception\AmbiguousPathException;
use Neos\OpenApi\Spec\ComponentsObject;
use Neos\OpenApi\Spec\ContactObject;
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Spec\LicenseObject;
use Neos\OpenApi\Spec\MediaTypeObject;
use Neos\OpenApi\Spec\MediaTypeObjectMap;
use Neos\OpenApi\Spec\OpenApiObject;
use Neos\OpenApi\Spec\OperationObject;
use Neos\OpenApi\Spec\ParameterObject;
use Neos\OpenApi\Spec\ParameterOrReferenceObjects;
use Neos\OpenApi\Spec\PathObject;
use Neos\OpenApi\Spec\PathsObject;
use Neos\OpenApi\Spec\ReferenceObject;
use Neos\OpenApi\Spec\RequestBodyObject;
use Neos\OpenApi\Spec\ResponseObject;
use Neos\OpenApi\Spec\ResponsesObject;
use Neos\OpenApi\Spec\SchemaObjectMap;
use Neos\OpenApi\Spec\SecurityRequirementObject;
use Neos\OpenApi\Spec\SecuritySchemeObject;
use Neos\OpenApi\Spec\SecuritySchemeOrReferenceObjectMap;
use Neos\OpenApi\Spec\ServerObject;
use Neos\OpenApi\Spec\ServerObjects;
use Neos\OpenApi\Spec\ServerVariableObject;
use Neos\OpenApi\Spec\ServerVariableObjects;
use Neos\OpenApi\Spec\SpecVersion;
use Neos\OpenApi\Spec\TagObject;
use Neos\OpenApi\Spec\TagObjects;
use Neos\OpenApi\Support\HttpMethod;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;
use Neos\OpenApi\Support\ParameterLocation;
use Neos\OpenApi\Support\RelativePath;
use PHPUnit\Framework\TestCase;

final class SpecModelTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function encode(\JsonSerializable $object): array
    {
        $decoded = json_decode((string) json_encode($object, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public function testAbsentMembersAreOmittedRatherThanRenderedAsNull(): void
    {
        self::assertSame(['name' => 'Ada'], $this->encode(new ContactObject(name: 'Ada')));
        self::assertSame([], $this->encode(new ContactObject()));
    }

    public function testTheVersionIsFixedAndLeadsTheDocument(): void
    {
        $document = $this->encode(new OpenApiObject(info: new InfoObject(title: 'Blog', version: '1.0.0')));

        self::assertSame(['openapi', 'info'], array_keys($document));
        self::assertSame(SpecVersion::VALUE, $document['openapi']);
        self::assertSame(['title' => 'Blog', 'version' => '1.0.0'], $document['info']);
    }

    /**
     * `ReferenceObject` refers to a specification object; a reference to a *schema* is a
     * `Neos\JsonSchema\ReferenceSchema`. Both render `$ref`, and they are deliberately not interchangeable.
     */
    public function testBothKindsOfReferenceRenderADollarRef(): void
    {
        self::assertSame(
            ['$ref' => '#/components/responses/NotFound', 'description' => 'The standard 404'],
            $this->encode(new ReferenceObject('#/components/responses/NotFound', description: 'The standard 404')),
        );
        self::assertSame(
            ['$ref' => '#/components/schemas/Post'],
            $this->encode(SchemaObjectMap::reference('Post')),
        );
    }

    public function testMutuallyExclusiveLicenseMembersAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LicenseObject(name: 'MIT', identifier: 'MIT', url: 'https://example.com');
    }

    public function testAPathParameterMustBeRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ParameterObject(name: 'id', in: ParameterLocation::path);
    }

    public function testDuplicateParametersInOneOperationAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ParameterOrReferenceObjects(
            new ParameterObject(name: 'q', in: ParameterLocation::query),
            new ParameterObject(name: 'q', in: ParameterLocation::query),
        );
    }

    /**
     * The same name in two *different* locations is a different parameter, and is allowed.
     */
    public function testTheSameParameterNameInTwoLocationsIsAllowed(): void
    {
        $parameters = new ParameterOrReferenceObjects(
            new ParameterObject(name: 'token', in: ParameterLocation::query),
            new ParameterObject(name: 'token', in: ParameterLocation::header),
        );

        self::assertCount(2, iterator_to_array($parameters));
    }

    public function testResponsesAreOrderedByStatusCodeWithDefaultLast(): void
    {
        $responses = ResponsesObject::create()
            ->withDefault(new ResponseObject('Unexpected error'))
            ->with(HttpStatusCode::fromInteger(404), new ResponseObject('Not Found'))
            ->with(HttpStatusCode::fromInteger(200), new ResponseObject('OK'));

        // PHP normalises the numeric keys to integers; the rendered document is a JSON object either way
        self::assertSame([200, 404, 'default'], array_keys($this->encode($responses)));
        self::assertTrue($responses->hasResponseForStatusCode(HttpStatusCode::fromInteger(404)));
    }

    public function testAnOutOfRangeStatusCodeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HttpStatusCode::fromInteger(99);
    }

    public function testStructurallyIdenticalPathsAreRejected(): void
    {
        $paths = PathsObject::create()->with(RelativePath::fromString('/users/{id}'), PathObject::create());

        $this->expectException(AmbiguousPathException::class);
        $paths->with(RelativePath::fromString('/users/{userId}'), PathObject::create());
    }

    /**
     * A concrete path must be matched before a template that would also swallow it, regardless of insertion order.
     */
    public function testAConcretePathIsOrderedAheadOfATemplateThatWouldMatchIt(): void
    {
        $paths = PathsObject::create()
            ->with(RelativePath::fromString('/users/{id}'), PathObject::create())
            ->with(RelativePath::fromString('/users/me'), PathObject::create());

        self::assertSame(['/users/me', '/users/{id}'], array_keys(iterator_to_array($paths)));
    }

    public function testMatchingAPathExtractsItsVariables(): void
    {
        $paths = PathsObject::create()->with(RelativePath::fromString('/users/{id}/posts/{slug}'), PathObject::create());

        self::assertNotNull($paths->match('/users/42/posts/hello', $variables));
        self::assertSame(['id' => '42', 'slug' => 'hello'], $variables);
        self::assertNull($paths->match('/users/42'));
    }

    public function testAPathTemplateReportsItsPlaceholders(): void
    {
        self::assertSame(['id', 'slug'], RelativePath::fromString('/users/{id}/posts/{slug}')->placeholders());
        self::assertSame([], RelativePath::fromString('/users')->placeholders());
    }

    public function testAPathMustStartWithASlash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RelativePath::fromString('users');
    }

    public function testOperationsAreAddressableByHttpMethod(): void
    {
        $get = new OperationObject(operationId: 'listUsers');
        $post = new OperationObject(operationId: 'createUser');
        $path = PathObject::create()
            ->withOperation(HttpMethod::GET, $get)
            ->withOperation(HttpMethod::POST, $post);

        self::assertSame($get, $path->operation(HttpMethod::GET));
        self::assertSame($post, $path->operation(HttpMethod::POST));
        self::assertNull($path->operation(HttpMethod::DELETE));
        self::assertSame([HttpMethod::GET, HttpMethod::POST], $path->allowedMethods());
        self::assertSame(['get', 'post'], array_keys(iterator_to_array($path->operations())));
    }

    public function testAddingAnOperationKeepsTheOtherMembers(): void
    {
        $path = (new PathObject(summary: 'Users', description: 'All of them'))
            ->withOperation(HttpMethod::GET, new OperationObject(operationId: 'listUsers'));

        self::assertSame('Users', $path->summary);
        self::assertSame('All of them', $path->description);
    }

    /**
     * The predecessor's equivalent silently dropped `security`, `tags` and `externalDocs`.
     */
    public function testAddingAPathKeepsEveryOtherMemberOfTheDocument(): void
    {
        $document = new OpenApiObject(
            info: new InfoObject(title: 'Blog', version: '1.0.0'),
            security: SecurityRequirementObject::scheme('bearerAuth'),
            tags: new TagObjects(new TagObject('Posts')),
            servers: new ServerObjects(new ServerObject('https://example.com')),
        );

        $withPath = $document->withAddedPath(RelativePath::fromString('/posts'), PathObject::create());

        self::assertNotNull($withPath->security);
        self::assertNotNull($withPath->tags);
        self::assertNotNull($withPath->servers);
        self::assertNotNull($withPath->paths);
    }

    public function testAMediaTypeMapPrefersAConcreteMatchOverAWildcard(): void
    {
        $json = new MediaTypeObject(schema: StringSchema::create());
        $any = new MediaTypeObject();
        $map = MediaTypeObjectMap::create()
            ->with(MediaTypeRange::fromString('*/*'), $any)
            ->with(MediaTypeRange::fromString('application/json'), $json);

        self::assertSame($json, $map->match(MediaTypeRange::fromString('application/json')));
        self::assertSame($any, $map->match(MediaTypeRange::fromString('text/plain')));
    }

    public function testAnInvalidMediaTypeRangeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MediaTypeRange::fromString('nonsense');
    }

    /**
     * A JSON Schema in the model is a `Neos\JsonSchema\Schema`, not a replica — in OpenAPI 3.1 a Schema Object
     * *is* a 2020-12 schema, so it renders straight through.
     */
    public function testAJsonSchemaRendersStraightThroughIntoTheDocument(): void
    {
        $body = new RequestBodyObject(
            content: MediaTypeObjectMap::json(new MediaTypeObject(
                schema: ObjectSchema::create(
                    properties: ObjectProperties::create(name: StringSchema::create(minLength: 1)),
                    required: ['name'],
                ),
            )),
            required: true,
        );

        self::assertSame(
            [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => ['name' => ['type' => 'string', 'minLength' => 1]],
                            'required' => ['name'],
                        ],
                    ],
                ],
                'required' => true,
            ],
            $this->encode($body),
        );
    }

    public function testARequestBodyMustDescribeAtLeastOneMediaType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RequestBodyObject(content: MediaTypeObjectMap::create());
    }

    public function testSecuritySchemesAreOnlyConstructibleInValidCombinations(): void
    {
        self::assertSame(
            ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
            $this->encode(SecuritySchemeObject::bearer()),
        );
        self::assertSame(
            ['type' => 'http', 'scheme' => 'basic'],
            $this->encode(SecuritySchemeObject::basic()),
        );
        self::assertSame(
            ['type' => 'apiKey', 'name' => 'X-Api-Key', 'in' => 'header'],
            $this->encode(SecuritySchemeObject::apiKey('X-Api-Key', \Neos\OpenApi\Support\SecuritySchemeApiKeyLocation::header)),
        );
        // takes only a url — the predecessor demanded an OAuthFlowsObject it would then have rejected
        self::assertSame(
            ['type' => 'openIdConnect', 'openIdConnectUrl' => 'https://example.com/.well-known/openid-configuration'],
            $this->encode(SecuritySchemeObject::openIdConnect('https://example.com/.well-known/openid-configuration')),
        );
    }

    /**
     * `bearerFormat` applies to the bearer scheme and nothing else, so a basic one carrying it would publish a
     * member that means nothing.
     */
    public function testABearerFormatIsRejectedOnAnyOtherHttpScheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SecuritySchemeObject::http('basic', bearerFormat: 'JWT');
    }

    public function testASecurityRequirementRendersAsAListOfAlternatives(): void
    {
        $requirement = SecurityRequirementObject::scopes('oauth2', ['read:posts'])->orElse(['bearerAuth' => []]);

        self::assertSame([['oauth2' => ['read:posts']], ['bearerAuth' => []]], $this->encode($requirement));
        self::assertSame(['oauth2', 'bearerAuth'], $requirement->schemeNames());
        self::assertFalse($requirement->anonymousAccessAllowed);
    }

    public function testAnEmptyAlternativeMeansAnonymousAccessIsAllowed(): void
    {
        $requirement = SecurityRequirementObject::scheme('bearerAuth')->orAnonymously();

        self::assertTrue($requirement->anonymousAccessAllowed);
        self::assertSame([['bearerAuth' => []], []], $this->encode($requirement));
    }

    public function testServerVariablesAreSubstitutedIntoTheUrl(): void
    {
        $server = new ServerObject(
            url: 'https://{region}.example.com/{basePath}',
            variables: ServerVariableObjects::create()
                ->with('region', new ServerVariableObject(default: 'eu'))
                ->with('basePath', new ServerVariableObject(default: 'v1')),
        );

        self::assertSame('https://eu.example.com/v1', $server->resolvedUrl());
        self::assertSame('https://us.example.com/v1', $server->resolvedUrl(['region' => 'us']));
    }

    public function testAServerVariableDefaultMustBeOneOfItsEnumValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ServerVariableObject(default: 'nope', enum: ['eu', 'us']);
    }

    public function testAnEmptyComponentsObjectKnowsItIsEmpty(): void
    {
        self::assertTrue((new ComponentsObject())->isEmpty());
        self::assertFalse((new ComponentsObject(schemas: SchemaObjectMap::create()->with('Contact', StringSchema::create())))->isEmpty());
    }

    /**
     * `components.parameters` is a map in the specification; the predecessor reused the *list* type here, which
     * would have emitted a JSON array.
     */
    public function testComponentsRenderTheirMapsAsJsonObjects(): void
    {
        $components = new ComponentsObject(
            schemas: SchemaObjectMap::create()->with('Name', StringSchema::create()),
            securitySchemes: SecuritySchemeOrReferenceObjectMap::create()->with('bearerAuth', SecuritySchemeObject::bearer()),
        );

        $encoded = (string) json_encode($components, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('"schemas":{"Name":', $encoded);
        self::assertStringContainsString('"securitySchemes":{"bearerAuth":', $encoded);
    }

    public function testDuplicateTagsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TagObjects(new TagObject('Posts'), new TagObject('Posts'));
    }

    /**
     * One end-to-end document, to pin the overall shape rather than each member in isolation. Note that `version`
     * comes after `contact` in the Info Object: members follow the order the specification lists them in.
     */
    public function testACompleteDocumentRendersAsExpected(): void
    {
        $document = new OpenApiObject(
            info: new InfoObject(title: 'Blog', version: '1.0.0', contact: new ContactObject(email: 'ada@example.com')),
            servers: new ServerObjects(new ServerObject('https://api.example.com')),
            paths: PathsObject::create()->with(
                RelativePath::fromString('/posts/{id}'),
                PathObject::create()->withOperation(HttpMethod::GET, new OperationObject(
                    tags: ['Posts'],
                    operationId: 'getPost',
                    parameters: new ParameterOrReferenceObjects(
                        new ParameterObject(name: 'id', in: ParameterLocation::path, required: true, schema: StringSchema::create()),
                    ),
                    responses: ResponsesObject::create()->with(
                        HttpStatusCode::fromInteger(200),
                        new ResponseObject('OK', content: MediaTypeObjectMap::json(new MediaTypeObject(
                            schema: SchemaObjectMap::reference('Post'),
                        ))),
                    ),
                )),
            ),
            components: new ComponentsObject(schemas: SchemaObjectMap::create()->with('Post', StringSchema::create())),
            tags: new TagObjects(new TagObject('Posts', 'Everything about posts')),
        );

        $expected = <<<'JSON'
        {"openapi":"3.1.1","info":{"title":"Blog","contact":{"email":"ada@example.com"},"version":"1.0.0"},"servers":[{"url":"https://api.example.com"}],"paths":{"/posts/{id}":{"get":{"tags":["Posts"],"operationId":"getPost","parameters":[{"name":"id","in":"path","required":true,"schema":{"type":"string"}}],"responses":{"200":{"description":"OK","content":{"application/json":{"schema":{"$ref":"#/components/schemas/Post"}}}}}}}},"components":{"schemas":{"Post":{"type":"string"}}},"tags":[{"name":"Posts","description":"Everything about posts"}]}
        JSON;

        self::assertSame($expected, json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
