<?php

declare(strict_types=1);

namespace Neos\OpenApi\Binding;

use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\NumberSchema;
use Neos\JsonSchema\Schema as JsonSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Validation\Normalization;
use Neos\OpenApi\Compilation\SchemaComponents;
use Neos\OpenApi\Dispatch\ArgumentSource;
use Neos\Schematic\SchemaNotProvided;
use Neos\Schematic\Schematic;

/**
 * Everything this package needs to know about one PHP type: how to describe it, how to read a value into it, and
 * how to write one back out.
 *
 * All three answers come from the schema the *type itself* owns, which is what stops the published document and
 * the runtime from disagreeing — describe and validate cannot drift apart when neither has a schema of its own to
 * drift with. `neos/schematic` is where that schema comes from, and this is the one place that reaches for it.
 *
 * Static, because there is nothing to hold: a schema is memoized by `Schematic` per class, so there is no state
 * here worth a constructor.
 */
final class TypeBinding
{
    private function __construct() {}

    /**
     * The `#/components/schemas` name a type claims, or `null` if it renders inline.
     *
     * Every type that corresponds to a PHP class gets a name — scalar-backed value objects included — so a type
     * used twice is visibly the same type. A plain `string` or `int` argument has no class and so no name.
     */
    public static function componentName(TypeReference $type): string|null
    {
        $className = $type->className();
        return $className === null ? null : SchemaHoister::componentName($className);
    }

    /**
     * The schema to put at the *use site*, registering this type and everything it contains as components.
     *
     * For a named type that is a `$ref`; for an anonymous one, the schema itself. The accumulator is threaded
     * through rather than returned because hoisting spans a whole document: two operations using the same type
     * must end up pointing at one entry.
     *
     * @throws SchemaNotProvided if the type owns no schema
     */
    public static function jsonSchema(TypeReference $type, SchemaComponents $components): JsonSchema
    {
        return SchemaHoister::hoist($type, self::schemaFor($type), $components);
    }

    /**
     * Turn raw request data — a query string value, a decoded JSON body — into an instance of this type.
     *
     * A JSON body arrives with real types, so `{"age": "45"}` is a string and is rejected as one. Every other
     * source is *always* a string — a path segment, a query value, a header line — so judging one that way would
     * reject everything, and it opts into scalar normalization instead.
     *
     * @throws SchemaNotProvided if the type owns no schema
     */
    #[\NoDiscard('inspect the CoercionOutcome; discarding it means the coercion was pointless')]
    public static function coerce(TypeReference $type, mixed $input, ArgumentSource $source = ArgumentSource::body): CoercionOutcome
    {
        if ($type->nullable && $input === null) {
            // `null` is the absence of a value, so there is nothing to build and nothing to validate
            return CoercionOutcome::ok(null);
        }
        $normalization = $source === ArgumentSource::body ? Normalization::None : Normalization::Scalars;
        $className = $type->className();
        $result = $className === null
            ? self::schemaFor($type)->validate($input, $normalization)
            : Schematic::map($className, $input, $normalization);
        return $result->valid ? CoercionOutcome::ok($result->value()) : CoercionOutcome::failed($result->issues);
    }

    /**
     * Read an instance back into `json_encode`-ready primitives, shaped the way the schema published for this
     * type describes them.
     *
     * The type is the *declared* one, which is the whole point: an empty PHP array is an empty JSON object or an
     * empty JSON array depending on what the document says, and the document says what this `TypeReference`
     * resolves to. Serializing through the runtime class instead would answer from a type the document never
     * mentions.
     *
     * Fails loudly rather than returning an outcome: unlike coercion, a failure here is never caused by the
     * caller's input — it raises `Neos\Schematic\UnextractableValue`, meaning the class does not expose the state
     * its own constructor names, which is a bug in the API rather than in the request.
     *
     * @throws SchemaNotProvided if the type owns no schema
     */
    #[\NoDiscard('inspect the returned primitives; discarding them means the serialization was pointless')]
    public static function serialize(TypeReference $type, mixed $value): mixed
    {
        return Schematic::serialize(self::schemaFor($type), $value);
    }

    /**
     * @throws SchemaNotProvided if the type owns no schema
     */
    private static function schemaFor(TypeReference $type): JsonSchema
    {
        $builtin = $type->builtinType();
        if ($builtin === null) {
            /** @var class-string $className */
            $className = $type->type;
            return Schematic::schemaFor($className);
        }
        // a builtin has no class to ask, so it maps straight onto the corresponding JSON Schema
        return match ($builtin) {
            BuiltinType::string => StringSchema::create(),
            BuiltinType::int => IntegerSchema::create(),
            BuiltinType::float => NumberSchema::create(),
            BuiltinType::bool => BooleanSchema::create(),
        };
    }
}
