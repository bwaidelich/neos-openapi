<?php

declare(strict_types=1);

namespace Neos\OpenApi\Binding;

use Neos\JsonSchema\Schema as JsonSchema;
use Neos\OpenApi\Compilation\SchemaComponents;

/**
 * Everything this package needs to know about one PHP type — and the only door through which a schema engine is
 * reached.
 *
 * Deliberately **one** port rather than separate "describe it" and "coerce it" ports: the schema a document
 * advertises and the schema the runtime enforces come from the same object, so they cannot drift apart. That is
 * also why serialization lives here and not on `json_encode` — a response body has to satisfy the schema that was
 * published for it.
 *
 * `Neos\OpenApi\Schematic\SchematicTypeBinding` is the only implementation, and nothing in core may name it
 * (enforced by `tests/Architecture`).
 */
interface TypeBinding
{
    /**
     * The `#/components/schemas` name this type claims, or `null` if it renders inline.
     *
     * Every type that corresponds to a PHP class gets a name — scalar-backed value objects included — so a type
     * used twice is visibly the same type. A plain `string` or `int` argument has no class and so no name.
     */
    public function componentName(): string|null;

    /**
     * The schema to put at the *use site*, registering this type and everything it contains as components.
     *
     * For a named type that is a `$ref`; for an anonymous one, the schema itself. The accumulator is threaded
     * through rather than returned because hoisting spans a whole document: two operations using the same type
     * must end up pointing at one entry.
     */
    public function jsonSchema(SchemaComponents $components): JsonSchema;

    /**
     * Turn raw request data — a query string value, a decoded JSON body — into an instance of this type.
     */
    #[\NoDiscard('inspect the CoercionOutcome; discarding it means the coercion was pointless')]
    public function coerce(mixed $input): CoercionOutcome;

    /**
     * Read an instance of this type back into `json_encode`-ready primitives.
     *
     * Fails loudly rather than returning an outcome: unlike coercion, a failure here is never caused by the
     * caller's input — it means the type and its schema disagree, which is a bug in the API, not in the request.
     */
    public function serialize(mixed $value): mixed;
}
