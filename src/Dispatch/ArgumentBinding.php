<?php

declare(strict_types=1);

namespace Neos\OpenApi\Dispatch;

use Neos\OpenApi\Binding\TypeReference;

/**
 * How one argument of an operation's method is filled from a request.
 *
 * Plain data: a {@see TypeReference} rather than a resolved binding, so a compiled API stays serializable and can
 * be cached whole. The provider turns it into a TypeBinding at request time, which is cheap because the schema
 * engine caches.
 */
final readonly class ArgumentBinding
{
    private function __construct(
        public string $argumentName,
        public TypeReference $type,
        public ArgumentSource $source,
        /**
         * The name this value carries on the wire, which is not always the argument's own name — a header may be
         * `X-Api-Key` while the argument is `$apiKey`. Meaningless for a body or an auth context.
         */
        public string $wireName,
        public bool $required,
    ) {}

    public static function fromRequest(
        string $argumentName,
        TypeReference $type,
        ArgumentSource $source,
        string $wireName,
        bool $required,
    ): self {
        return new self($argumentName, $type, $source, $wireName, $required);
    }

    public static function body(string $argumentName, TypeReference $type, bool $required): self
    {
        return new self($argumentName, $type, ArgumentSource::body, $argumentName, $required);
    }

    public static function authContext(string $argumentName, TypeReference $type): self
    {
        return new self($argumentName, $type, ArgumentSource::authContext, $argumentName, true);
    }

    public static function request(string $argumentName, TypeReference $type): self
    {
        return new self($argumentName, $type, ArgumentSource::request, $argumentName, true);
    }
}
