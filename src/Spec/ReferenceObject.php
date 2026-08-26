<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;

/**
 * A `$ref` in a position that holds a *specification object* — a parameter, a response, a security scheme.
 *
 * Not to be confused with `Neos\JsonSchema\ReferenceSchema`, which is a `$ref` in a position that holds a
 * *schema*. Both render `{"\$ref": …}`, but they are not interchangeable, and the type system says so: a
 * `#/components/schemas/…` reference is a ReferenceSchema (see {@see SchemaObjectMap::reference()}).
 *
 * @see https://spec.openapis.org/oas/v3.1.1#reference-object
 */
final readonly class ReferenceObject implements JsonSerializable
{
    public function __construct(
        public string $ref,
        public string|null $summary = null,
        public string|null $description = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        // `$ref` is not a valid PHP property name, so this one cannot use the shared trait
        $result = ['$ref' => $this->ref];
        if ($this->summary !== null) {
            $result['summary'] = $this->summary;
        }
        if ($this->description !== null) {
            $result['description'] = $this->description;
        }
        return $result;
    }
}
