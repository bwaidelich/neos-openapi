<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#example-object
 */
final readonly class ExampleObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    /**
     * @param array<mixed>|bool|float|int|string|null $value
     */
    public function __construct(
        public string|null $summary = null,
        public string|null $description = null,
        public array|bool|float|int|string|null $value = null,
        public string|null $externalValue = null,
    ) {
        if ($value !== null && $externalValue !== null) {
            throw new \InvalidArgumentException('The "value" and "externalValue" members of an Example Object are mutually exclusive', 1783500130);
        }
    }
}
