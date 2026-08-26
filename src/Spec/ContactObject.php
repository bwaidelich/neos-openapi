<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#contact-object
 */
final readonly class ContactObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public string|null $name = null,
        public string|null $url = null,
        public string|null $email = null,
    ) {}
}
