<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\JsonSchema\Schema as JsonSchema;
use Neos\OpenApi\Support\ParameterLocation;
use Neos\OpenApi\Support\ParameterStyle;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * Carries no runtime metadata: which method argument this parameter feeds lives in the Dispatch Table, not here
 * (ADR 0003).
 *
 * @see https://spec.openapis.org/oas/v3.1.1#parameter-object
 */
final readonly class ParameterObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public string $name,
        public ParameterLocation $in,
        public string|null $description = null,
        public bool|null $required = null,
        public bool|null $deprecated = null,
        public ParameterStyle|null $style = null,
        public bool|null $explode = null,
        public bool|null $allowReserved = null,
        public JsonSchema|null $schema = null,
        public ExampleOrReferenceObjectMap|null $examples = null,
    ) {
        if ($in === ParameterLocation::path && $required !== true) {
            throw new \InvalidArgumentException(sprintf('The path parameter "%s" must be required', $name), 1783500141);
        }
    }
}
