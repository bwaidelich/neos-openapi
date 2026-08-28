<?php

declare(strict_types=1);

namespace Neos\OpenApi\Schematic;

use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\ArraySchema as JsonArraySchema;
use Neos\JsonSchema\NullSchema;
use Neos\JsonSchema\ObjectSchema as JsonObjectSchema;
use Neos\JsonSchema\Schema as JsonSchema;
use Neos\JsonSchema\Support\ObjectProperties;
use Neos\OpenApi\Compilation\SchemaComponents;
use Neos\OpenApi\Spec\SchemaObjectMap;
use Neos\Schematic\Schema\Kind;
use Neos\Schematic\Schema\Schema;

/**
 * Renders a `neos/schematic` schema graph into JSON Schema, lifting every named type out into
 * `#/components/schemas` and leaving a `$ref` behind.
 *
 * This is the OpenAPI-specific counterpart to `Schema::toJsonSchema()`, and the reason it lives here rather than
 * upstream: `#/components/schemas/…` is a location in an *OpenAPI document*, which `neos/schematic` has no business
 * knowing about. The duplicated rendering is the price of that separation, and it is a small one.
 *
 * Every node carrying a `target` becomes a component — scalar-backed value objects included — so a type used in
 * two places is one entry pointed at twice rather than two identical inline schemas.
 *
 * @internal to the adapter; core reaches this through {@see \Neos\OpenApi\Binding\TypeBinding::jsonSchema()}
 */
final class SchemaHoister
{
    /**
     * The schema to place at the use site, with everything it contains registered in $components.
     */
    public function hoist(Schema $node, SchemaComponents $components): JsonSchema
    {
        $target = $node->target;
        if ($target === null) {
            return $this->nullableIfNeeded($node, $this->body($node, $components));
        }
        $name = self::componentName($target);
        if (!$components->has($name, $target)) {
            // Registering *before* recursing would guard against cycles — but a schematic graph cannot contain
            // one (children are built before their parent, and generation rejects recursion outright), so
            // rendering the body first is safe, and it keeps collision detection honest.
            $components->register($name, $target, $this->body($node, $components));
        }
        return $this->nullableIfNeeded($node, SchemaObjectMap::reference($name));
    }

    /**
     * The short class name, which is what makes a published document readable. Collisions are the caller's
     * problem to hear about, and {@see SchemaComponents} raises them.
     *
     */
    public static function componentName(string $className): string
    {
        $position = strrpos($className, '\\');
        return $position === false ? $className : substr($className, $position + 1);
    }

    /**
     * The type's own schema, ignoring both its name and its nullability — what a component entry contains.
     */
    private function body(Schema $node, SchemaComponents $components): JsonSchema
    {
        return match ($node->kind) {
            Kind::Scalar => $node->jsonSchema,
            Kind::Object => $this->object($node, $components),
            Kind::List => $this->list($node, $components),
        };
    }

    private function object(Schema $node, SchemaComponents $components): JsonSchema
    {
        if ($node->properties === [] || !$node->jsonSchema instanceof JsonObjectSchema) {
            return $node->jsonSchema;
        }
        $properties = [];
        foreach ($node->properties as $name => $property) {
            $properties[$name] = $this->hoist($property, $components);
        }
        return $node->jsonSchema->with(properties: ObjectProperties::create(...$properties));
    }

    private function list(Schema $node, SchemaComponents $components): JsonSchema
    {
        if ($node->items === null || !$node->jsonSchema instanceof JsonArraySchema) {
            return $node->jsonSchema;
        }
        return $node->jsonSchema->with(items: $this->hoist($node->items, $components));
    }

    /**
     * Nullability belongs at the *use site*, never inside the component: `Foo` is one type whether or not a
     * particular property may omit it.
     */
    private function nullableIfNeeded(Schema $node, JsonSchema $rendered): JsonSchema
    {
        return $node->nullable ? AnyOfSchema::create($rendered, NullSchema::create()) : $rendered;
    }
}
