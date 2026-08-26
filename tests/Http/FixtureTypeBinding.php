<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http;

use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\NumberSchema;
use Neos\JsonSchema\Schema as JsonSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Validation\Issue;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\JsonSchema\Validation\Issues;
use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\CoercionOutcome;
use Neos\OpenApi\Binding\TypeBinding;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Compilation\SchemaComponents;
use Neos\OpenApi\Tests\Http\Fixtures\Coercible;

/**
 * Coerces and serializes through {@see Coercible}, and normalises the builtins the way a query string needs —
 * `?limit=3` arrives as a string and has to reach an `int` argument.
 */
final readonly class FixtureTypeBinding implements TypeBinding
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
            return self::builtinSchema($this->type->builtinType());
        }
        return $components->register($name, $className, StringSchema::create(title: $name));
    }

    public function coerce(mixed $input): CoercionOutcome
    {
        if ($input === null) {
            return $this->type->nullable
                ? CoercionOutcome::ok(null)
                : self::rejected(IssueCode::InvalidType, 'A value is required');
        }
        $className = $this->type->className();
        if ($className === null) {
            return $this->coerceBuiltin($input);
        }
        if (!is_a($className, Coercible::class, true)) {
            throw new \LogicException(sprintf('The fixture type "%s" cannot be coerced', $className), 1783500420);
        }
        return $className::coerce($input);
    }

    public function serialize(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }
        if (!$value instanceof Coercible) {
            throw new \LogicException(sprintf('The fixture type "%s" cannot be serialized', get_debug_type($value)), 1783500421);
        }
        return $value->serialize();
    }

    private function coerceBuiltin(mixed $input): CoercionOutcome
    {
        return match ($this->type->builtinType()) {
            BuiltinType::string => is_string($input)
                ? CoercionOutcome::ok($input)
                : self::rejected(IssueCode::InvalidType, 'Expected a string'),
            BuiltinType::int => is_int($input) || (is_string($input) && preg_match('/^-?\d+$/', $input) === 1)
                ? CoercionOutcome::ok((int) $input)
                : self::rejected(IssueCode::InvalidType, 'Expected an integer'),
            BuiltinType::float => is_numeric($input)
                ? CoercionOutcome::ok((float) $input)
                : self::rejected(IssueCode::InvalidType, 'Expected a number'),
            BuiltinType::bool => in_array($input, [true, false, 'true', 'false'], true)
                ? CoercionOutcome::ok($input === true || $input === 'true')
                : self::rejected(IssueCode::InvalidType, 'Expected a boolean'),
            null => throw new \LogicException('A type is neither a class nor a builtin', 1783500422),
        };
    }

    private static function rejected(IssueCode $code, string $message): CoercionOutcome
    {
        return CoercionOutcome::failed(Issues::create(Issue::create([], $code, $message)));
    }

    private static function builtinSchema(BuiltinType|null $builtin): JsonSchema
    {
        return match ($builtin) {
            BuiltinType::string, null => StringSchema::create(),
            BuiltinType::int => IntegerSchema::create(),
            BuiltinType::float => NumberSchema::create(),
            BuiltinType::bool => BooleanSchema::create(),
        };
    }
}
