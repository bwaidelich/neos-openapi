<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic;

use Neos\OpenApi\ApiDefinition;
use Neos\OpenApi\Compilation\ApiCompiler;
use Neos\OpenApi\Problem\ProblemDocument;
use Neos\OpenApi\Schematic\SchematicTypeBindingProvider;
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Support\HttpMethod;
use Neos\OpenApi\Support\RelativePath;
use Neos\OpenApi\Tests\Schematic\Fixtures\BlogApi;
use Neos\Schematic\Attributes\ReflectionMiddleware;
use Neos\Schematic\Schematic;
use PHPUnit\Framework\TestCase;

/**
 * End to end: a real API compiled through the real `neos/schematic` adapter.
 *
 * `ApiCompilerTest` covers the compiler itself against a stub provider, which is what proves core needs no engine.
 * This is the other half — that the two actually fit together.
 */
final class CompilationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function compile(): array
    {
        $compiler = new ApiCompiler(new SchematicTypeBindingProvider(Schematic::create(new ReflectionMiddleware())));
        $compiled = $compiler->compile(
            ApiDefinition::create(info: new InfoObject(title: 'Blog', version: '1.0.0'))
                ->withOperationsFrom(BlogApi::class, tag: 'Blog'),
        );
        $decoded = json_decode((string) json_encode($compiled->document, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
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

    public function testEveryDomainTypeReachedBecomesAComponent(): void
    {
        $schemas = $this->arrayAt($this->compile(), 'components', 'schemas');

        self::assertSame(
            ['Author', 'AuthorName', 'AuthorNames', 'Block', 'ImageBlock', 'PostCount', 'PostStatus', 'ProblemDocument', 'TextBlock'],
            array_keys($schemas),
        );
    }

    /**
     * A response header typed by a domain value object is described by the *component* that object generated —
     * the same one the body would use — rather than by a second, inline copy of its schema.
     */
    public function testAResponseHeaderIsDescribedByTheComponentItsTypeGenerated(): void
    {
        $header = $this->arrayAt($this->compile(), 'paths', '/authors', 'post', 'responses', 201, 'headers', 'X-Author-Name');

        self::assertSame('Who was created', $header['description']);
        self::assertTrue($header['required']);
        self::assertSame(['$ref' => '#/components/schemas/AuthorName'], $header['schema']);
    }

    public function testAValueObjectPropertyIsReferencedNotInlined(): void
    {
        $author = $this->arrayAt($this->compile(), 'components', 'schemas', 'Author');

        self::assertSame(
            [
                'name' => ['$ref' => '#/components/schemas/AuthorName'],
                'posts' => ['$ref' => '#/components/schemas/PostCount'],
                'pseudonym' => ['anyOf' => [['$ref' => '#/components/schemas/AuthorName'], ['type' => 'null']]],
            ],
            $this->arrayAt($author, 'properties'),
        );
    }

    public function testAnEnumQueryParameterKeepsItsCases(): void
    {
        $document = $this->compile();
        $parameter = $this->arrayAt($document, 'paths', '/authors', 'get', 'parameters', 0);

        self::assertSame('status', $parameter['name']);
        self::assertSame('query', $parameter['in']);
        self::assertArrayNotHasKey('required', $parameter);
        self::assertSame(
            ['anyOf' => [['$ref' => '#/components/schemas/PostStatus'], ['type' => 'null']]],
            $this->arrayAt($parameter, 'schema'),
        );
        self::assertSame(['draft', 'published'], $this->arrayAt($document, 'components', 'schemas', 'PostStatus', 'enum'));
    }

    public function testAPolymorphicRequestBodyKeepsItsDiscriminator(): void
    {
        $document = $this->compile();

        self::assertSame(
            ['$ref' => '#/components/schemas/Block'],
            $this->arrayAt($document, 'paths', '/blocks', 'post', 'requestBody', 'content', 'application/json', 'schema'),
        );
        self::assertSame(
            [
                'propertyName' => 'kind',
                'mapping' => ['text' => '#/components/schemas/TextBlock', 'image' => '#/components/schemas/ImageBlock'],
            ],
            $this->arrayAt($document, 'components', 'schemas', 'Block', 'discriminator'),
        );
    }

    public function testAPathParameterIsRequiredAndReferencesItsType(): void
    {
        $parameter = $this->arrayAt($this->compile(), 'paths', '/authors/{name}', 'get', 'parameters', 0);

        self::assertSame('name', $parameter['name']);
        self::assertSame('path', $parameter['in']);
        self::assertTrue($parameter['required']);
        self::assertSame(['$ref' => '#/components/schemas/AuthorName'], $this->arrayAt($parameter, 'schema'));
    }

    /**
     * The 400 advertises the schema of the payload the runtime emits, and that schema is generated from the class
     * itself rather than hand-written into the compiler.
     */
    public function testTheAdvertisedProblemDocumentMatchesTheOneTheRuntimeEmits(): void
    {
        $advertised = $this->arrayAt($this->compile(), 'components', 'schemas', 'ProblemDocument');

        $expected = json_decode((string) json_encode(ProblemDocument::schema(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($expected, $advertised);
    }

    public function testTheDispatchTableAndTheDocumentAgree(): void
    {
        $compiler = new ApiCompiler(new SchematicTypeBindingProvider(Schematic::create(new ReflectionMiddleware())));
        $compiled = $compiler->compile(
            ApiDefinition::create(info: new InfoObject(title: 'Blog', version: '1.0.0'))->withOperationsFrom(BlogApi::class),
        );

        $entry = $compiled->dispatchTable->find(RelativePath::fromString('/blocks'), HttpMethod::POST);
        self::assertNotNull($entry);
        self::assertSame(BlogApi::class, $entry->apiClassName);
        self::assertSame('addBlock', $entry->methodName);
        self::assertSame('body', $entry->arguments[0]->source->value);

        // and the binding the runtime will use coerces a real payload
        $binding = (new SchematicTypeBindingProvider(Schematic::create(new ReflectionMiddleware())))
            ->for($entry->arguments[0]->type);
        $outcome = $binding->coerce(['kind' => 'text', 'body' => 'Hello']);
        self::assertTrue($outcome->success);
        self::assertInstanceOf(Fixtures\TextBlock::class, $outcome->value());
    }
}
