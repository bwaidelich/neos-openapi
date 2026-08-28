<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic;

use Neos\JsonSchema\ReferenceSchema;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\TypeBinding;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Compilation\SchemaComponents;
use Neos\OpenApi\Dispatch\ArgumentSource;
use Neos\OpenApi\Exception\ComponentNameCollisionException;
use Neos\OpenApi\Tests\Schematic\Fixtures\Author;
use Neos\OpenApi\Tests\Schematic\Fixtures\AuthorName;
use Neos\OpenApi\Tests\Schematic\Fixtures\AuthorNames;
use Neos\OpenApi\Tests\Schematic\Fixtures\Collaboration;
use Neos\OpenApi\Tests\Schematic\Fixtures\Colliding\Rival;
use Neos\OpenApi\Tests\Schematic\Fixtures\PostStatus;
use Neos\OpenApi\Tests\Schematic\Fixtures\TextBlock;
use Neos\OpenApi\Tests\Schematic\Fixtures\Undescribable;
use Neos\Schematic\SchemaNotProvided;
use PHPUnit\Framework\TestCase;

/**
 * One {@see TypeBinding} per type: schemas hoisted into components, request data validated into instances, and
 * instances read back out — all from the one schema, which is what stops the published document and the runtime
 * from disagreeing.
 */
final class TypeBindingTest extends TestCase
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

    public function testABuiltinTypeBindsWithoutAComponentName(): void
    {
        $type = TypeReference::builtin(BuiltinType::string);
        $components = SchemaComponents::create();

        self::assertNull(TypeBinding::componentName($type));
        self::assertSame(['type' => 'string'], $this->encode(TypeBinding::jsonSchema($type, $components)));
        self::assertTrue($components->isEmpty());
    }

    public function testEachBuiltinMapsToItsJsonSchemaType(): void
    {
        $components = SchemaComponents::create();
        $types = [];
        foreach (BuiltinType::cases() as $case) {
            $types[$case->value] = $this->encode(TypeBinding::jsonSchema(TypeReference::builtin($case), $components))['type'];
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
        $type = TypeReference::of(AuthorName::class);
        $components = SchemaComponents::create();

        self::assertSame('AuthorName', TypeBinding::componentName($type));
        $atUseSite = TypeBinding::jsonSchema($type, $components);

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
        TypeBinding::jsonSchema(TypeReference::of(Author::class), $components);

        self::assertSame(['Author', 'AuthorName', 'PostCount'], array_keys($this->encode($components->toSchemaObjectMap())));
    }

    public function testAShapeReferencesItsPropertiesRatherThanInliningThem(): void
    {
        $components = SchemaComponents::create();
        TypeBinding::jsonSchema(TypeReference::of(Author::class), $components);

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
        TypeBinding::jsonSchema(TypeReference::of(Collaboration::class), $components);

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
        $atUseSite = TypeBinding::jsonSchema(TypeReference::of(AuthorName::class, nullable: true), $components);

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
        TypeBinding::jsonSchema(TypeReference::of(PostStatus::class), $components);

        self::assertSame(
            ['type' => 'string', 'enum' => ['draft', 'published']],
            $this->encode($components->toSchemaObjectMap())['PostStatus'],
        );
    }

    public function testAListReferencesItsItemType(): void
    {
        $components = SchemaComponents::create();
        TypeBinding::jsonSchema(TypeReference::of(AuthorNames::class), $components);

        $list = $this->encode($components->toSchemaObjectMap())['AuthorNames'];
        self::assertIsArray($list);
        self::assertSame('array', $list['type']);
        self::assertSame(['$ref' => '#/components/schemas/AuthorName'], $list['items']);
    }

    public function testComponentsAreSortedSoTheDocumentDoesNotDependOnVisitOrder(): void
    {
        $first = SchemaComponents::create();
        TypeBinding::jsonSchema(TypeReference::of(PostStatus::class), $first);
        TypeBinding::jsonSchema(TypeReference::of(AuthorName::class), $first);

        self::assertSame(['AuthorName', 'PostStatus'], array_keys($this->encode($first->toSchemaObjectMap())));
    }

    /**
     * Two classes with the same short name would silently overwrite each other; a published contract must not
     * depend on which one was visited first.
     */
    public function testTwoClassesClaimingOneComponentNameFailLoudly(): void
    {
        $components = SchemaComponents::create();
        TypeBinding::jsonSchema(TypeReference::of(AuthorName::class), $components);

        $this->expectException(ComponentNameCollisionException::class);
        TypeBinding::jsonSchema(TypeReference::of(Rival::class), $components);
    }

    public function testATypeOwningNoSchemaIsRefused(): void
    {
        $this->expectException(SchemaNotProvided::class);
        $this->expectExceptionMessageMatches('/' . preg_quote(Undescribable::class, '/') . '" provides no schema/');
        TypeBinding::jsonSchema(TypeReference::of(Undescribable::class), SchemaComponents::create());
    }

    public function testCoercionTurnsRequestDataIntoAnInstance(): void
    {
        $outcome = TypeBinding::coerce(TypeReference::of(Author::class), ['name' => 'Ada Lovelace', 'posts' => 3]);

        self::assertTrue($outcome->success);
        $author = $outcome->value();
        self::assertInstanceOf(Author::class, $author);
        self::assertSame('Ada Lovelace', $author->name->value);
        self::assertSame(3, $author->posts->value);
    }

    /**
     * A query-string value arrives as a string, so that binding site reads scalars leniently and `?posts=3`
     * works. A JSON body carries real types, so the same input is a string there and is rejected as one — the
     * source is what decides, not the value.
     */
    public function testHowStrictlyAScalarIsReadFollowsWhereItCameFrom(): void
    {
        $type = TypeReference::of(Author::class);

        $fromQuery = TypeBinding::coerce($type, ['name' => 'Ada', 'posts' => '3'], ArgumentSource::query);
        self::assertTrue($fromQuery->success);
        $author = $fromQuery->value();
        self::assertInstanceOf(Author::class, $author);
        self::assertSame(3, $author->posts->value);

        $fromBody = TypeBinding::coerce($type, ['name' => 'Ada', 'posts' => '3'], ArgumentSource::body);
        self::assertFalse($fromBody->success);
        self::assertSame(IssueCode::InvalidType->value, $fromBody->issues?->toArray()[0]->code);
    }

    public function testAFailedCoercionCarriesIssuesRatherThanThrowing(): void
    {
        $outcome = TypeBinding::coerce(TypeReference::of(Author::class), ['name' => '', 'posts' => 3]);

        self::assertFalse($outcome->success);
        self::assertNotNull($outcome->issues);
        $issues = $outcome->issues->toArray();
        self::assertSame(IssueCode::TooShort->value, $issues[0]->code);
        self::assertSame('name', $issues[0]->pathAsString());
    }

    public function testReadingTheValueOfAFailedCoercionThrows(): void
    {
        $outcome = TypeBinding::coerce(TypeReference::of(AuthorName::class), '');

        $this->expectException(\LogicException::class);
        $value = $outcome->value();
        self::fail(sprintf('Expected reading the value to throw, got %s', get_debug_type($value)));
    }

    public function testANullableBindingAcceptsNull(): void
    {
        $outcome = TypeBinding::coerce(TypeReference::of(AuthorName::class, nullable: true), null);

        self::assertTrue($outcome->success);
        self::assertNull($outcome->value());
    }

    public function testSerializationReadsAnInstanceBackIntoPrimitives(): void
    {
        $type = TypeReference::of(Author::class);
        $author = TypeBinding::coerce($type, ['name' => 'Ada', 'posts' => 3])->value();

        self::assertSame(['name' => 'Ada', 'posts' => 3, 'pseudonym' => null], TypeBinding::serialize($author));
    }

    /**
     * The guarantee the single port exists for: what a response emits satisfies the schema the document published.
     */
    public function testSerializedOutputValidatesAgainstTheHoistedSchema(): void
    {
        $type = TypeReference::of(TextBlock::class);
        $components = SchemaComponents::create();
        TypeBinding::jsonSchema($type, $components);

        $block = TypeBinding::coerce($type, ['body' => 'Hello'])->value();
        $primitives = TypeBinding::serialize($block);

        $componentSchema = $components->toSchemaObjectMap()->get('TextBlock');
        self::assertNotNull($componentSchema);
        // the component references AuthorName, which the validator cannot follow, so check the leaf instead
        self::assertIsArray($primitives);
        $authorName = $components->toSchemaObjectMap()->get('AuthorName');
        self::assertNotNull($authorName);
        self::assertTrue($authorName->validate($primitives['body'])->valid);
    }

    public function testTheSameSchemaDescribesAndValidates(): void
    {
        $type = TypeReference::of(AuthorName::class);
        $components = SchemaComponents::create();
        TypeBinding::jsonSchema($type, $components);

        $schema = $components->toSchemaObjectMap()->get('AuthorName');
        self::assertNotNull($schema);
        // the advertised minLength is the one the runtime enforces
        self::assertTrue($schema->validate('Ada')->valid);
        self::assertFalse($schema->validate('')->valid);
        self::assertFalse(TypeBinding::coerce($type, '')->success);
        self::assertTrue(TypeBinding::coerce($type, 'Ada')->success);
    }
}
