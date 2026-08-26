<?php

declare(strict_types=1);

namespace Neos\OpenApi\Support;

use JsonSerializable;

/**
 * The version of the *API being described* — not the version of the OpenAPI specification, which is fixed (see
 * {@see \Neos\OpenApi\Spec\SpecVersion}).
 */
final readonly class ApiVersion implements JsonSerializable
{
    private function __construct(
        public string $value,
    ) {
        if ($value === '') {
            throw new \InvalidArgumentException('An API version must not be empty', 1783500100);
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
