<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\ParameterStyle;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#encoding-object
 */
final readonly class EncodingObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public string|null $contentType = null,
        public HeaderOrReferenceObjectMap|null $headers = null,
        public ParameterStyle|null $style = null,
        public bool|null $explode = null,
        // spelled `allowReserverd` in the predecessor, which would have emitted an unknown member
        public bool|null $allowReserved = null,
    ) {}
}
