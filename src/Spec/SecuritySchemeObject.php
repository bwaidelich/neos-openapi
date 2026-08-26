<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SecuritySchemeApiKeyLocation;
use Neos\OpenApi\Support\SecuritySchemeType;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * Which members apply depends entirely on the `type`, so the constructor is private and each type has its own
 * named constructor — the combinations the specification forbids are simply not expressible.
 *
 * @see https://spec.openapis.org/oas/v3.1.1#security-scheme-object
 */
final readonly class SecuritySchemeObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    private function __construct(
        public SecuritySchemeType $type,
        public string|null $description = null,
        public string|null $name = null,
        public SecuritySchemeApiKeyLocation|null $in = null,
        public string|null $scheme = null,
        public string|null $bearerFormat = null,
        public OAuthFlowsObject|null $flows = null,
        public string|null $openIdConnectUrl = null,
    ) {
        // the specification says `bearerFormat` applies to `http ("bearer")` and nothing else, so a basic scheme
        // carrying one would publish a member that means nothing
        if ($bearerFormat !== null && strcasecmp((string) $scheme, 'bearer') !== 0) {
            throw new \InvalidArgumentException(sprintf(
                'A bearerFormat applies to the "bearer" scheme only, not to "%s"',
                (string) $scheme,
            ), 1783500122);
        }
    }

    public static function apiKey(string $name, SecuritySchemeApiKeyLocation $in, string|null $description = null): self
    {
        return new self(type: SecuritySchemeType::apiKey, description: $description, name: $name, in: $in);
    }

    public static function http(string $scheme, string|null $bearerFormat = null, string|null $description = null): self
    {
        return new self(type: SecuritySchemeType::http, description: $description, scheme: $scheme, bearerFormat: $bearerFormat);
    }

    /**
     * The common case of {@see self::http()}: `Authorization: Bearer <token>`.
     */
    public static function bearer(string $bearerFormat = 'JWT', string|null $description = null): self
    {
        return self::http(scheme: 'bearer', bearerFormat: $bearerFormat, description: $description);
    }

    /**
     * The other common case of {@see self::http()}: `Authorization: Basic <base64(user:password)>`.
     *
     * The scheme carries no `realm`, because the Security Scheme Object has nowhere to put one. The
     * `Neos\OpenApi\Http\RequestHandler` derives the realm of its `WWW-Authenticate` challenge from the
     * document's own `info.title` instead — the protection space is the API the document describes.
     */
    public static function basic(string|null $description = null): self
    {
        return self::http(scheme: 'basic', description: $description);
    }

    public static function mutualTLS(string|null $description = null): self
    {
        return new self(type: SecuritySchemeType::mutualTLS, description: $description);
    }

    public static function oauth2(OAuthFlowsObject $flows, string|null $description = null): self
    {
        return new self(type: SecuritySchemeType::oauth2, description: $description, flows: $flows);
    }

    /**
     * Takes only the url. The predecessor's equivalent demanded an `OAuthFlowsObject` it then discarded — and
     * would have thrown had one actually been passed on, since `flows` does not apply to this type.
     */
    public static function openIdConnect(string $openIdConnectUrl, string|null $description = null): self
    {
        return new self(type: SecuritySchemeType::openIdConnect, description: $description, openIdConnectUrl: $openIdConnectUrl);
    }
}
