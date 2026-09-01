<?php

declare(strict_types=1);

namespace Neos\OpenApi\Binding;

use Neos\JsonSchema\ArraySchema as JsonArraySchema;
use Neos\JsonSchema\Nullable;
use Neos\JsonSchema\ObjectSchema as JsonObjectSchema;
use Neos\JsonSchema\Schema as JsonSchema;
use Neos\JsonSchema\Support\ObjectProperties;
use Neos\OpenApi\Compilation\SchemaComponents;
use Neos\OpenApi\Spec\SchemaObjectMap;
use Neos\Schematic\Reflection\ClassShape;
use Neos\Schematic\Reflection\Nature;

/**
 * Lifts every named type out of a schema into `#/components/schemas`, leaving a `$ref` behind.
 *
 * `neos/schematic` hands out one *self-contained* schema per class — a nested value object's constraints are
 * embedded, which is what lets one validation pass report every issue in a payload. A document wants the opposite:
 * a type used in two places should be one entry pointed at twice, so that it is visibly the same type. So this
 * walks the class's {@see ClassShape} alongside its schema and swaps each class-typed child for a reference to its
 * own component.
 *
 * Structure comes from the shape, constraints from the schema — which is why the walk needs both. "Which types get
 * a component name" is an OpenAPI question, so the cost of that walk belongs here rather than upstream.
 *
 * @internal reached through {@see TypeBinding::jsonSchema()}
 */
final class SchemaHoister
{
    private function __construct() {}

    /**
     * The schema to place at the use site, with everything it contains registered in $components.
     */
    public static function hoist(TypeReference $type, JsonSchema $schema, SchemaComponents $components): JsonSchema
    {
        $className = $type->className();
        if ($className === null) {
            // a builtin has no class, so there is nothing to name and nothing to hoist
            return self::nullableIfNeeded($type, $schema);
        }
        $name = self::componentName($className);
        if (!$components->has($name, $className)) {
            // Registering *before* recursing would guard against cycles — but schematic rejects recursive types
            // outright, so rendering the body first is safe, and it keeps collision detection honest.
            $components->register($name, $className, self::body($className, $components));
        }
        return self::nullableIfNeeded($type, SchemaObjectMap::reference($name));
    }

    /**
     * The short class name, which is what makes a published document readable. Collisions are the caller's
     * problem to hear about, and {@see SchemaComponents} raises them.
     */
    public static function componentName(string $className): string
    {
        $position = strrpos($className, '\\');
        return $position === false ? $className : substr($className, $position + 1);
    }

    /**
     * The type's own schema with its class-typed children hoisted out — what a component entry contains.
     *
     * @type T of ProvidesSchema
     * @param class-string<T> $className
     */
    private static function body(string $className, SchemaComponents $components): JsonSchema
    {
        $schema = $className::schema();
        $shape = ClassShape::of($className);
        return match ($shape->nature) {
            // a leaf: there is no child that could become a component of its own
            Nature::Enum, Nature::Scalar => $schema,
            Nature::ListOf => self::list($schema, $shape, $components),
            Nature::Shape => self::object($schema, $shape, $components),
        };
    }

    /**
     * Only the properties the *constructor* types by a class are replaced; everything else stays exactly as the
     * schema declared it, so a hand-written `schema()` keeps whatever it says about its own leaves.
     */
    private static function object(JsonSchema $schema, ClassShape $shape, SchemaComponents $components): JsonSchema
    {
        if (!$schema instanceof JsonObjectSchema || $schema->properties === null) {
            return $schema;
        }
        $properties = [];
        foreach ($schema->properties as $name => $propertySchema) {
            $type = self::typeOf($shape->parameter($name));
            $properties[$name] = $type === null ? $propertySchema : self::hoist($type, $propertySchema, $components);
        }
        return $schema->with(properties: ObjectProperties::create(...$properties));
    }

    private static function list(JsonSchema $schema, ClassShape $shape, SchemaComponents $components): JsonSchema
    {
        $type = self::typeOf($shape->parameters[0] ?? null);
        if (!$schema instanceof JsonArraySchema || !$schema->items instanceof JsonSchema || $type === null) {
            return $schema;
        }
        return $schema->with(items: self::hoist($type, $schema->items, $components));
    }

    /**
     * The class a constructor parameter is typed by, or `null` when it is a builtin — the two cases being
     * "becomes a component" and "stays inline".
     */
    private static function typeOf(\ReflectionParameter|null $parameter): TypeReference|null
    {
        $type = $parameter?->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }
        /** @var class-string $className */
        $className = $type->getName();
        return TypeReference::of($className, $type->allowsNull());
    }

    /**
     * Nullability belongs at the *use site*, never inside the component: `Foo` is one type whether or not a
     * particular property may omit it.
     */
    private static function nullableIfNeeded(TypeReference $type, JsonSchema $rendered): JsonSchema
    {
        return $type->nullable ? Nullable::wrap($rendered) : $rendered;
    }
}
