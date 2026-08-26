<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation;

use Neos\JsonSchema\Schema as JsonSchema;
use Neos\JsonSchema\StringSchema;
use Neos\OpenApi\Binding\CoercionOutcome;
use Neos\OpenApi\Binding\TypeBinding;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Compilation\SchemaComponents;

/**
 * Describes every type as a string, naming classes after their short name so component behaviour is still
 * observable. Coercion and serialization are pass-throughs: what these tests are about is *compilation*.
 */
final readonly class StubTypeBinding implements TypeBinding
{
    public function __construct(
        private TypeReference $type,
    ) {}

    public function componentName(): string|null
    {
        $className = $this->type->className();
        if ($className === null) {
            return null;
        }
        $position = strrpos($className, '\\');
        return $position === false ? $className : substr($className, $position + 1);
    }

    public function jsonSchema(SchemaComponents $components): JsonSchema
    {
        $className = $this->type->className();
        $name = $this->componentName();
        if ($className === null || $name === null) {
            return StringSchema::create(title: $this->type->describe());
        }
        return $components->register($name, $className, StringSchema::create(title: $name));
    }

    public function coerce(mixed $input): CoercionOutcome
    {
        return CoercionOutcome::ok($input);
    }

    public function serialize(mixed $value): mixed
    {
        return $value;
    }
}
