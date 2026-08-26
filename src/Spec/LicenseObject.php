<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#license-object
 */
final readonly class LicenseObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public string $name,
        public string|null $identifier = null,
        public string|null $url = null,
    ) {
        if ($identifier !== null && $url !== null) {
            throw new \InvalidArgumentException('The "identifier" and "url" members of a License Object are mutually exclusive', 1783500110);
        }
    }
}
