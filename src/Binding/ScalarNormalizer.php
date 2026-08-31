<?php

declare(strict_types=1);

namespace Neos\OpenApi\Binding;

use Neos\JsonSchema\AllOfSchema;
use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\NullSchema;
use Neos\JsonSchema\NumberSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\OneOfSchema;
use Neos\JsonSchema\Schema as JsonSchema;

/**
 * Reads a request parameter as the type its schema declares: `"45"` -> `45`, `"true"` -> `true`.
 *
 * Best-effort and constraint-free: a value it cannot read as the declared type is passed through unchanged, so the
 * validator that runs next reports the precise violation instead.
 *
 * A parameter arrives as a string or as arrays of strings, never as a `stdClass`, which is also all the schema
 * engine accepts — see {@see \Neos\JsonSchema\Validation\Validator}.
 */
final class ScalarNormalizer
{
    private function __construct() {}

    public static function normalize(JsonSchema $schema, mixed $input): mixed
    {
        if ($schema instanceof IntegerSchema) {
            return is_string($input) && preg_match('/^-?\d+$/', $input) === 1 ? (int) $input : $input;
        }
        if ($schema instanceof NumberSchema) {
            return is_string($input) && is_numeric($input) ? $input + 0 : $input;
        }
        if ($schema instanceof BooleanSchema) {
            // the spellings OpenAPI's `form` style produces; anything else – "yes", "on", "" – stays what it is
            return match ($input) {
                'true', '1', 1 => true,
                'false', '0', 0 => false,
                default => $input,
            };
        }
        if ($schema instanceof ObjectSchema) {
            return self::object($schema, $input);
        }
        if ($schema instanceof ArraySchema) {
            return self::array($schema, $input);
        }
        if ($schema instanceof AnyOfSchema || $schema instanceof OneOfSchema) {
            return self::branches($schema, $input);
        }
        if ($schema instanceof AllOfSchema) {
            foreach ($schema as $branch) {
                $input = self::normalize($branch, $input);
            }
            return $input;
        }
        return $input;
    }

    private static function object(ObjectSchema $schema, mixed $input): mixed
    {
        if (!is_array($input) || ($input !== [] && array_is_list($input)) || $schema->properties === null) {
            return $input;
        }
        $normalized = [];
        foreach ($input as $key => $value) {
            $propertySchema = is_string($key) ? $schema->properties->get($key) : null;
            $normalized[$key] = $propertySchema === null ? $value : self::normalize($propertySchema, $value);
        }
        return $normalized;
    }

    private static function array(ArraySchema $schema, mixed $input): mixed
    {
        if (!is_array($input) || ($input !== [] && !array_is_list($input))) {
            return $input;
        }
        $normalized = [];
        foreach ($input as $index => $item) {
            $itemSchema = $schema->itemSchema($index);
            $normalized[] = $itemSchema instanceof JsonSchema ? self::normalize($itemSchema, $item) : $item;
        }
        return $normalized;
    }

    /**
     * A union is read through the branch the normalized value ends up matching – the first one that does, since a
     * value cannot be two shapes at once.
     *
     * When it matches none, the canonical "nullable" idiom `anyOf: [<something>, {"type": "null"}]` still has an
     * answer: the value was meant to be that one substantive branch, so read it through that one and let the
     * validator report why it is not. A genuine multi-branch union has no such answer and is passed through
     * untouched. This mirrors how the validator picks the issues it reports for the same two cases – a change to
     * one is a change to the other.
     */
    private static function branches(AnyOfSchema|OneOfSchema $schema, mixed $input): mixed
    {
        $substantiveBranches = [];
        foreach ($schema as $branch) {
            $normalized = self::normalize($branch, $input);
            if ($branch->validate($normalized)->valid) {
                return $normalized;
            }
            if (!$branch instanceof NullSchema) {
                $substantiveBranches[] = $branch;
            }
        }
        if (count($substantiveBranches) === 1) {
            return self::normalize($substantiveBranches[0], $input);
        }
        return $input;
    }
}
