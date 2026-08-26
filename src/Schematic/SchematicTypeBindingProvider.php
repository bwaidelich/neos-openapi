<?php

declare(strict_types=1);

namespace Neos\OpenApi\Schematic;

use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\NumberSchema;
use Neos\JsonSchema\StringSchema;
use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\TypeBinding;
use Neos\OpenApi\Binding\TypeBindingProvider;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Exception\UnsupportedTypeException;
use Neos\Schematic\Generation\UnsupportedTypeException as SchematicUnsupportedTypeException;
use Neos\Schematic\Instantiation\Mapper;
use Neos\Schematic\Schema\Schema;
use Neos\Schematic\Schematic;
use Neos\Schematic\Serialization\Serializer;

/**
 * Backs the {@see TypeBindingProvider} port with `neos/schematic`.
 *
 * Classes are described by the wired `Schematic` — so whichever middleware chain a project registered (attributes,
 * `ProvidesSchema`, its own) is what describes its types, and a caching middleware in that chain is what keeps
 * this cheap enough to call per request. Builtins have no class for schematic to reflect, so they are mapped
 * directly onto the corresponding JSON Schema.
 */
final readonly class SchematicTypeBindingProvider implements TypeBindingProvider
{
    private Mapper $mapper;
    private Serializer $serializer;
    private SchemaHoister $hoister;

    public function __construct(
        private Schematic $schematic,
        Mapper|null $mapper = null,
        Serializer|null $serializer = null,
    ) {
        $this->mapper = $mapper ?? new Mapper();
        $this->serializer = $serializer ?? new Serializer();
        $this->hoister = new SchemaHoister();
    }

    public function for(TypeReference $type): TypeBinding
    {
        $schema = $this->schemaFor($type);
        return new SchematicTypeBinding(
            $type->nullable ? $schema->asNullable() : $schema,
            $this->mapper,
            $this->serializer,
            $this->hoister,
        );
    }

    private function schemaFor(TypeReference $type): Schema
    {
        $builtin = $type->type;
        if ($builtin instanceof BuiltinType) {
            return Schema::scalar(match ($builtin) {
                BuiltinType::string => StringSchema::create(),
                BuiltinType::int => IntegerSchema::create(),
                BuiltinType::float => NumberSchema::create(),
                BuiltinType::bool => BooleanSchema::create(),
            });
        }
        try {
            return $this->schematic->schemaFor($builtin);
        } catch (SchematicUnsupportedTypeException $exception) {
            // translated at the seam, so core never sees a schematic exception
            throw UnsupportedTypeException::for($type, $exception->getMessage());
        }
    }
}
