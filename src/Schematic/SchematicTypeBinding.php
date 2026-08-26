<?php

declare(strict_types=1);

namespace Neos\OpenApi\Schematic;

use Neos\JsonSchema\Schema as JsonSchema;
use Neos\OpenApi\Binding\CoercionOutcome;
use Neos\OpenApi\Binding\TypeBinding;
use Neos\OpenApi\Compilation\SchemaComponents;
use Neos\Schematic\Instantiation\Mapper;
use Neos\Schematic\Schema\Schema;
use Neos\Schematic\Serialization\Serializer;

/**
 * The one implementation of {@see TypeBinding}: a `neos/schematic` {@see Schema} plus the services that operate on
 * it.
 *
 * All three answers come from that single schema — the JSON Schema a document advertises, the coercion a request
 * goes through, and the serialization a response goes through — so they cannot disagree.
 *
 * @internal obtained from {@see SchematicTypeBindingProvider}
 */
final readonly class SchematicTypeBinding implements TypeBinding
{
    public function __construct(
        private Schema $schema,
        private Mapper $mapper,
        private Serializer $serializer,
        private SchemaHoister $hoister,
    ) {}

    public function componentName(): string|null
    {
        $target = $this->schema->target;
        return $target === null ? null : SchemaHoister::componentName($target);
    }

    public function jsonSchema(SchemaComponents $components): JsonSchema
    {
        return $this->hoister->hoist($this->schema, $components);
    }

    public function coerce(mixed $input): CoercionOutcome
    {
        $result = $this->mapper->map($this->schema, $input);
        if (!$result->success) {
            return CoercionOutcome::failed($result->issues);
        }
        return CoercionOutcome::ok($result->value());
    }

    public function serialize(mixed $value): mixed
    {
        // A failure here raises Neos\Schematic\Serialization\UnextractableValue, which is a programming error
        // (the type and its schema disagree) and must not be reported to a caller as a bad request.
        return $this->serializer->serialize($this->schema, $value);
    }
}
