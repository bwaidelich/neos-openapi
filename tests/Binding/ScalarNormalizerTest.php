<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Binding;

use Neos\JsonSchema\AllOfSchema;
use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\Nullable;
use Neos\JsonSchema\NumberSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\OneOfSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ArrayItems;
use Neos\JsonSchema\Support\ObjectProperties;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\OpenApi\Binding\ScalarNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Reading a parameter that is *always* a string as the type its schema declares — the leniency a JSON body must
 * not get, kept out of the schema engine and next to the binding site that knows which of the two it is holding.
 */
#[CoversClass(ScalarNormalizer::class)]
final class ScalarNormalizerTest extends TestCase
{
    private static function contact(): ObjectSchema
    {
        return ObjectSchema::create(
            properties: ObjectProperties::create(
                name: StringSchema::create(minLength: 1, maxLength: 200),
                age: IntegerSchema::create(minimum: 0, maximum: 150),
            ),
            additionalProperties: false,
            required: ['name', 'age'],
        );
    }

    public function testANumericStringIsReadAsAnInteger(): void
    {
        self::assertSame(45, ScalarNormalizer::normalize(IntegerSchema::create(), '45'));
        self::assertSame(-45, ScalarNormalizer::normalize(IntegerSchema::create(), '-45'));
    }

    public function testANumericStringIsReadAsANumber(): void
    {
        self::assertSame(4.5, ScalarNormalizer::normalize(NumberSchema::create(), '4.5'));
    }

    public function testABooleanIsReadFromItsQueryParameterSpellings(): void
    {
        $schema = BooleanSchema::create();

        self::assertTrue(ScalarNormalizer::normalize($schema, 'true'));
        self::assertTrue(ScalarNormalizer::normalize($schema, '1'));
        self::assertTrue(ScalarNormalizer::normalize($schema, 1));
        self::assertFalse(ScalarNormalizer::normalize($schema, 'false'));
        self::assertFalse(ScalarNormalizer::normalize($schema, '0'));
        self::assertFalse(ScalarNormalizer::normalize($schema, 0));
    }

    /**
     * Anything else stays what it is, so that the validator running next reports the precise violation instead of
     * this guessing at one.
     */
    public function testWhatItCannotReadIsLeftForTheValidatorToReject(): void
    {
        $schema = BooleanSchema::create();

        self::assertSame('yes', ScalarNormalizer::normalize($schema, 'yes'));
        self::assertSame('', ScalarNormalizer::normalize($schema, ''));
        self::assertSame('45.5', ScalarNormalizer::normalize(IntegerSchema::create(), '45.5'));
        self::assertFalse(IntegerSchema::create()->validate(ScalarNormalizer::normalize(IntegerSchema::create(), '45.5'))->valid);
    }

    /**
     * Reading a value is not validating one: a value that is the declared *type* can still break the schema's
     * constraints, and does so as loudly as before.
     */
    public function testReadingAValueCannotMakeAnInvalidOneValid(): void
    {
        $schema = IntegerSchema::create(minimum: 1);
        $result = $schema->validate(ScalarNormalizer::normalize($schema, '0'));

        self::assertFalse($result->valid);
        self::assertSame([IssueCode::TooSmall->value], $result->issues->codes());
    }

    public function testDescendsIntoTheDeclaredPropertiesOfAnObject(): void
    {
        $normalized = ScalarNormalizer::normalize(self::contact(), ['name' => 'Ada', 'age' => '36']);

        self::assertSame(['name' => 'Ada', 'age' => 36], $normalized);
        self::assertTrue(self::contact()->validate($normalized)->valid);
    }

    public function testDescendsIntoNestedObjects(): void
    {
        $schema = ObjectSchema::create(properties: ObjectProperties::create(contact: self::contact()));

        self::assertSame(
            ['contact' => ['name' => 'Ada', 'age' => 36]],
            ScalarNormalizer::normalize($schema, ['contact' => ['name' => 'Ada', 'age' => '36']]),
        );
    }

    public function testDescendsIntoListItems(): void
    {
        $schema = ArraySchema::create(items: IntegerSchema::create());

        self::assertSame([1, 2, 3], ScalarNormalizer::normalize($schema, ['1', '2', '3']));
    }

    public function testEachTupleItemIsReadUnderItsOwnPrefixSchema(): void
    {
        $schema = ArraySchema::create(items: false, prefixItems: ArrayItems::create(StringSchema::create(), IntegerSchema::create()));

        self::assertSame(['Ada', 36], ScalarNormalizer::normalize($schema, ['Ada', '36']));
    }

    public function testItemsNothingConstrainsAreLeftUntouched(): void
    {
        self::assertSame(['1', true, null], ScalarNormalizer::normalize(ArraySchema::create(), ['1', true, null]));
    }

    /**
     * The nullable idiom is a union, so a wrapped parameter is read through its one substantive branch — which is
     * what lets a query parameter be both optional and typed.
     */
    public function testANullableValueIsReadThroughItsSubstantiveBranch(): void
    {
        $schema = Nullable::wrap(IntegerSchema::create(minimum: 1));

        self::assertNull(ScalarNormalizer::normalize($schema, null));
        self::assertSame(45, ScalarNormalizer::normalize($schema, '45'));
    }

    /**
     * Matching no branch, the nullable idiom still reports *why the value is not the substantive one* — which it
     * can only do if that branch's reading was the one applied.
     */
    public function testAValueMatchingNoBranchIsStillReadThroughTheOnlySubstantiveOne(): void
    {
        $schema = Nullable::wrap(self::contact());
        $normalized = ScalarNormalizer::normalize($schema, ['name' => 'Ada', 'age' => '36', 'extra' => true]);

        self::assertSame([IssueCode::UnrecognizedKeys->value], $schema->validate($normalized)->issues->codes());
    }

    public function testAUnionIsReadThroughTheBranchTheValueMatches(): void
    {
        $schema = OneOfSchema::create(
            self::contact(),
            ObjectSchema::create(properties: ObjectProperties::create(nickname: StringSchema::create()), additionalProperties: false),
        );

        self::assertSame(['name' => 'Ada', 'age' => 36], ScalarNormalizer::normalize($schema, ['name' => 'Ada', 'age' => '36']));
        self::assertSame(['nickname' => 'Ada'], ScalarNormalizer::normalize($schema, ['nickname' => 'Ada']));
    }

    /**
     * A genuine multi-branch union has no single answer to read through, so the value is handed on as it is.
     */
    public function testAGenuineUnionMatchingNoBranchIsLeftAlone(): void
    {
        $schema = AnyOfSchema::create(IntegerSchema::create(), BooleanSchema::create());

        self::assertSame('nope', ScalarNormalizer::normalize($schema, 'nope'));
    }

    public function testAnAllOfIsFoldedThroughEveryBranch(): void
    {
        $schema = AllOfSchema::create(IntegerSchema::create(), IntegerSchema::create(minimum: 1));

        self::assertSame(45, ScalarNormalizer::normalize($schema, '45'));
    }

    public function testAStringSchemaIsLeftAlone(): void
    {
        self::assertSame('45', ScalarNormalizer::normalize(StringSchema::create(), '45'));
    }
}
