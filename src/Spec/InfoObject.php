<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\ApiVersion;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#info-object
 */
final readonly class InfoObject implements JsonSerializable
{
    public ApiVersion $version;

    public function __construct(
        public string $title,
        ApiVersion|string $version,
        public string|null $summary = null,
        public string|null $description = null,
        public string|null $termsOfService = null,
        public ContactObject|null $contact = null,
        public LicenseObject|null $license = null,
    ) {
        $this->version = is_string($version) ? ApiVersion::fromString($version) : $version;
    }

    /**
     * Written out rather than derived from `get_object_vars()`: `$version` has to be declared separately (so the
     * constructor can accept a plain string for it), and that would otherwise put it *before* `title` in the
     * output, since member order follows declaration order.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $members = [
            'title' => $this->title,
            'summary' => $this->summary,
            'description' => $this->description,
            'termsOfService' => $this->termsOfService,
            'contact' => $this->contact,
            'license' => $this->license,
            'version' => $this->version,
        ];
        return array_filter($members, static fn(mixed $member): bool => $member !== null);
    }
}
