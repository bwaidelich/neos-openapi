<?php

declare(strict_types=1);

namespace Neos\OpenApi\Binding;

/**
 * A PHP type, described as **data**: either a builtin or the name of a class, plus nullability.
 *
 * This is what a compiled API records about an operation's arguments and return values — never a
 * `ReflectionType`. Reflection happens once, at compile time; what survives into the cached artifact has to be
 * plain serializable data, which is also what lets a request be served without reflecting anything.
 *
 * The two possibilities are one non-nullable member rather than two nullable ones, so "exactly one of them is
 * set" is a fact about the type rather than an invariant every reader has to trust.
 *
 * Rendering a schema for one, or reading a value into it, is {@see TypeBinding}.
 */
final readonly class TypeReference
{
    /**
     * @param BuiltinType|class-string $type
     */
    private function __construct(
        public BuiltinType|string $type,
        public bool $nullable,
    ) {}

    /**
     * @param class-string $className
     */
    public static function of(string $className, bool $nullable = false): self
    {
        return new self($className, $nullable);
    }

    public static function builtin(BuiltinType $builtin, bool $nullable = false): self
    {
        return new self($builtin, $nullable);
    }

    public function asNullable(): self
    {
        if ($this->nullable) {
            return $this;
        }
        return new self($this->type, true);
    }

    /**
     * @return class-string|null the class this refers to, or null if it refers to a builtin
     */
    public function className(): string|null
    {
        return is_string($this->type) ? $this->type : null;
    }

    public function builtinType(): BuiltinType|null
    {
        return $this->type instanceof BuiltinType ? $this->type : null;
    }

    /**
     * How to name this type in an error message.
     */
    public function describe(): string
    {
        $name = is_string($this->type) ? $this->type : $this->type->value;
        return $this->nullable ? $name . '|null' : $name;
    }
}
