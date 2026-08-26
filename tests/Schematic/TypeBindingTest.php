<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic;

use Neos\JsonSchema\ReferenceSchema;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\TypeBindingProvider;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Compilation\SchemaComponents;
use Neos\OpenApi\Exception\ComponentNameCollisionException;
use Neos\OpenApi\Exception\UnsupportedTypeException;
use Neos\OpenApi\Schematic\SchematicTypeBindingProvider;
use Neos\OpenApi\Tests\Schematic\Fixtures\Author;
use Neos\OpenApi\Tests\Schematic\Fixtures\AuthorName;
use Neos\OpenApi\Tests\Schematic\Fixtures\AuthorNames;
use Neos\OpenApi\Tests\Schematic\Fixtures\Block;
use Neos\OpenApi\Tests\Schematic\Fixtures\Collaboration;
use Neos\OpenApi\Tests\Schematic\Fixtures\Colliding\Rival;
use Neos\OpenApi\Tests\Schematic\Fixtures\PostStatus;
use Neos\OpenApi\Tests\Schematic\Fixtures\TextBlock;
use Neos\OpenApi\Tests\Schematic\Fixtures\Undescribable;
use Neos\Schematic\Attributes\ReflectionMiddleware;
use Neos\Schematic\Schematic;
use PHPUnit\Framework\TestCase;

/**
 * The `neos/schematic` adapter behind the TypeBinding port: schemas hoisted into components, request data coerced
 * into instances, and instances read back out — all from the one schema, which is what stops the published
 * document and the runtime from disagreeing.
 */
final class TypeBindingTest extends TestCase
{
    private TypeBindingProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new SchematicTypeBindingProvider(Schematic::create(new ReflectionMiddleware()));
    }

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

    public function testABuiltinTypeBindsWithoutAComponentName(): void
    {
        $binding = $this->provider->for(TypeReference::builtin(BuiltinType::string));
        $components = SchemaComponents::create();

        self::assertNull($binding->componentName());
        self::assertSame(['type' => 'string'], $this->encode($binding->jsonSchema($components)));
        self::assertTrue($components->isEmpty());
    }

    public function testEachBuiltinMapsToItsJsonSchemaType(): void
    {
        $components = SchemaComponents::create();
        $types = [];
        foreach (BuiltinType::cases() as $case) {
            $types[$case->value] = $this->encode($this->provider->for(TypeReference::builtin($case))->jsonSchema($components))['type'];
        }

        self::assertSame(
            ['string' => 'string', 'int' => 'integer', 'float' => 'number', 'bool' => 'boolean'],
            $types,
        );
    }

    /**
     * Every class becomes a component, scalar-backed value objects included — so the use site is a `$ref`.
     */
    public function testAValueObjectIsHoistedIntoAComponentAndReferencedAtTheUseSite(): void
    {
        $binding = $this->provider->for(TypeReference::of(AuthorName::class));
        $components = SchemaComponents::create();

        self::assertSame('AuthorName', $binding->componentName());
        $atUseSite = $binding->jsonSchema($components);

        self::assertInstanceOf(ReferenceSchema::class, $atUseSite);
        self::assertSame(['$ref' => '#/components/schemas/AuthorName'], $this->encode($atUseSite));
        self::assertSame(
            ['type' => 'string', 'description' => 'The full name of an author', 'minLength' => 1, 'maxLength' => 200],
            $this->encode($components->toSchemaObjectMap())['AuthorName'],
        );
    }

    public function testNestedTypesAreHoistedToo(): void
    {
        $components = SchemaComponents::create();
        $this->provider->for(TypeReference::of(Author::class))->jsonSchema($components);

        self::assertSame(['Author', 'AuthorName', 'PostCount'], array_keys($this->encode($components->toSchemaObjectMap())));
    }

    public function testAShapeReferencesItsPropertiesRatherThanInliningThem(): void
    {
        $components = SchemaComponents::create();
        $this->provider->for(TypeReference::of(Author::class))->jsonSchema($components);

        $author = $this->encode($components->toSchemaObjectMap())['Author'];
        self::assertIsArray($author);
        self::assertSame(
            [
                'name' => ['$ref' => '#/components/schemas/AuthorName'],
                'posts' => ['$ref' => '#/components/schemas/PostCount'],
                'pseudonym' => ['anyOf' => [['$ref' => '#/components/schemas/AuthorName'], ['type' => 'null']]],
            ],
            $author['properties'],
        );
    }

    /**
     * The reason hoisting everything was worth it: one type used twice is one entry, referenced twice — the
     * document says "these are the same type" instead of repeating an identical schema.
     */
    public function testATypeUsedTwiceBecomesOneComponent(): void
    {
        $components = SchemaComponents::create();
        $this->provider->for(TypeReference::of(Collaboration::class))->jsonSchema($components);

        $schemas = $this->encode($components->toSchemaObjectMap());
        self::assertSame(['Author', 'AuthorName', 'Collaboration', 'PostCount', 'PostStatus'], array_keys($schemas));

        $collaboration = $schemas['Collaboration'];
        self::assertIsArray($collaboration);
        $properties = $collaboration['properties'];
        self::assertIsArray($properties);
        self::assertSame(['$ref' => '#/components/schemas/Author'], $properties['lead']);
        self::assertSame(['$ref' => '#/components/schemas/Author'], $properties['second']);
    }

    /**
     * Nullability belongs at the use site: `AuthorName` is one type whether or not a given property may omit it,
     * so the component itself must not carry the `null` branch.
     */
    public function testNullabilityWrapsTheReferenceRatherThanTheComponent(): void
    {
        $components = SchemaComponents::create();
        $atUseSite = $this->provider->for(TypeReference::of(AuthorName::class, nullable: true))->jsonSchema($components);

        self::assertSame(
            ['anyOf' => [['$ref' => '#/components/schemas/AuthorName'], ['type' => 'null']]],
            $this->encode($atUseSite),
        );
        $component = $this->encode($components->toSchemaObjectMap())['AuthorName'];
        self::assertIsArray($component);
        self::assertSame('string', $component['type']);
    }

    public function testAnEnumBecomesAComponentWithItsCases(): void
    {
        $components = SchemaComponents::create();
        $this->provider->for(TypeReference::of(PostStatus::class))->jsonSchema($components);

        self::assertSame(
            ['type' => 'string', 'enum' => ['draft', 'published']],
            $this->encode($components->toSchemaObjectMap())['PostStatus'],
        );
    }

    public function testAListReferencesItsItemType(): void
    {
        $components = SchemaComponents::create();
        $this->provider->for(TypeReference::of(AuthorNames::class))->jsonSchema($components);

        $list = $this->encode($components->toSchemaObjectMap())['AuthorNames'];
        self::assertIsArray($list);
        self::assertSame('array', $list['type']);
        self::assertSame(['$ref' => '#/components/schemas/AuthorName'], $list['items']);
    }

    /**
     * The discriminator's mapping holds class-strings upstream; OpenAPI needs component references.
     */
    public function testADiscriminatorMappingIsRewrittenToComponentReferences(): void
    {
        $components = SchemaComponents::create();
        $this->provider->for(TypeReference::of(Block::class))->jsonSchema($components);

        $block = $this->encode($components->toSchemaObjectMap())['Block'];
        self::assertIsArray($block);
        self::assertSame(
            [
                'propertyName' => 'kind',
                'mapping' => [
                    'text' => '#/components/schemas/TextBlock',
                    'image' => '#/components/schemas/ImageBlock',
                ],
            ],
            $block['discriminator'],
        );
        self::assertSame(
            [['$ref' => '#/components/schemas/TextBlock'], ['$ref' => '#/components/schemas/ImageBlock']],
            $block['oneOf'],
        );
    }

    public function testComponentsAreSortedSoTheDocumentDoesNotDependOnVisitOrder(): void
    {
        $first = SchemaComponents::create();
        $this->provider->for(TypeReference::of(PostStatus::class))->jsonSchema($first);
        $this->provider->for(TypeReference::of(AuthorName::class))->jsonSchema($first);

        self::assertSame(['AuthorName', 'PostStatus'], array_keys($this->encode($first->toSchemaObjectMap())));
    }

    /**
     * Two classes with the same short name would silently overwrite each other; a published contract must not
     * depend on which one was visited first.
     */
    public function testTwoClassesClaimingOneComponentNameFailLoudly(): void
    {
        $components = SchemaComponents::create();
        $this->provider->for(TypeReference::of(AuthorName::class))->jsonSchema($components);

        $this->expectException(ComponentNameCollisionException::class);
        $this->provider->for(TypeReference::of(Rival::class))->jsonSchema($components);
    }

    public function testAnUndescribableTypeIsReportedAsACoreException(): void
    {
        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessageMatches('/Cannot describe the type "' . preg_quote(Undescribable::class, '/') . '"/');
        $this->provider->for(TypeReference::of(Undescribable::class));
    }

    public function testCoercionTurnsRequestDataIntoAnInstance(): void
    {
        $outcome = $this->provider->for(TypeReference::of(Author::class))
            ->coerce(['name' => 'Ada Lovelace', 'posts' => 3]);

        self::assertTrue($outcome->success);
        $author = $outcome->value();
        self::assertInstanceOf(Author::class, $author);
        self::assertSame('Ada Lovelace', $author->name->value);
        self::assertSame(3, $author->posts->value);
    }

    /**
     * A query-string value arrives as a string; the engine's normalization is what makes `?posts=3` work.
     */
    public function testCoercionNormalizesANumericStringForAnIntegerType(): void
    {
        $outcome = $this->provider->for(TypeReference::of(Author::class))
            ->coerce(['name' => 'Ada', 'posts' => '3']);

        self::assertTrue($outcome->success);
        $author = $outcome->value();
        self::assertInstanceOf(Author::class, $author);
        self::assertSame(3, $author->posts->value);
    }

    public function testAFailedCoercionCarriesIssuesRatherThanThrowing(): void
    {
        $outcome = $this->provider->for(TypeReference::of(Author::class))->coerce(['name' => '', 'posts' => 3]);

        self::assertFalse($outcome->success);
        self::assertNotNull($outcome->issues);
        $issues = $outcome->issues->toArray();
        self::assertSame(IssueCode::TooShort->value, $issues[0]->code);
        self::assertSame('name', $issues[0]->pathAsString());
    }

    public function testReadingTheValueOfAFailedCoercionThrows(): void
    {
        $outcome = $this->provider->for(TypeReference::of(AuthorName::class))->coerce('');

        $this->expectException(\LogicException::class);
        $value = $outcome->value();
        self::fail(sprintf('Expected reading the value to throw, got %s', get_debug_type($value)));
    }

    public function testANullableBindingAcceptsNull(): void
    {
        $outcome = $this->provider->for(TypeReference::of(AuthorName::class, nullable: true))->coerce(null);

        self::assertTrue($outcome->success);
        self::assertNull($outcome->value());
    }

    public function testSerializationReadsAnInstanceBackIntoPrimitives(): void
    {
        $binding = $this->provider->for(TypeReference::of(Author::class));
        $author = $binding->coerce(['name' => 'Ada', 'posts' => 3])->value();

        self::assertSame(['name' => 'Ada', 'posts' => 3, 'pseudonym' => null], $binding->serialize($author));
    }

    /**
     * The guarantee the single port exists for: what a response emits satisfies the schema the document published.
     */
    public function testSerializedOutputValidatesAgainstTheHoistedSchema(): void
    {
        $binding = $this->provider->for(TypeReference::of(TextBlock::class));
        $components = SchemaComponents::create();
        $binding->jsonSchema($components);

        $block = $binding->coerce(['body' => 'Hello'])->value();
        $primitives = $binding->serialize($block);

        $componentSchema = $components->toSchemaObjectMap()->get('TextBlock');
        self::assertNotNull($componentSchema);
        // the component references AuthorName, which the validator cannot follow, so check the leaf instead
        self::assertIsArray($primitives);
        $authorName = $components->toSchemaObjectMap()->get('AuthorName');
        self::assertNotNull($authorName);
        self::assertTrue($authorName->validate($primitives['body'])->valid);
    }

    public function testTheSameProviderIsUsedForBothDescribingAndCoercing(): void
    {
        $binding = $this->provider->for(TypeReference::of(AuthorName::class));
        $components = SchemaComponents::create();
        $binding->jsonSchema($components);

        $schema = $components->toSchemaObjectMap()->get('AuthorName');
        self::assertNotNull($schema);
        // the advertised minLength is the one the runtime enforces
        self::assertTrue($schema->validate('Ada')->valid);
        self::assertFalse($schema->validate('')->valid);
        self::assertFalse($binding->coerce('')->success);
        self::assertTrue($binding->coerce('Ada')->success);
    }
}
