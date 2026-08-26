<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\RelativePath;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * A complete OpenAPI document — the root object, and the thing you publish.
 *
 * `openapi` is not an ordinary constructor argument: it defaults to {@see SpecVersion::VALUE} and nothing else
 * chooses it — the one exception is {@see \Neos\OpenApi\Compilation\ApiCompiler}, which passes
 * {@see SpecVersion::ITEM_SCHEMA_VALUE} for itself when a document needs a 3.2-only field. The document is
 * render-only; there is deliberately no way to read one back in.
 *
 * @see https://spec.openapis.org/oas/v3.1.1#openapi-object
 */
final readonly class OpenApiObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public string $openapi;

    public function __construct(
        public InfoObject $info,
        public ServerObjects|null $servers = null,
        public PathsObject|null $paths = null,
        public ComponentsObject|null $components = null,
        public SecurityRequirementObject|null $security = null,
        public TagObjects|null $tags = null,
        public ExternalDocumentationObject|null $externalDocs = null,
        public string|null $jsonSchemaDialect = null,
        string|null $openapi = null,
    ) {
        $this->openapi = $openapi ?? SpecVersion::VALUE;
    }

    /**
     * Unlike the predecessor's equivalent, this carries *every* member over — that one silently dropped
     * `security`, `tags` and `externalDocs` each time a path was added.
     */
    public function withAddedPath(RelativePath $path, PathObject $object): self
    {
        return new self(
            info: $this->info,
            servers: $this->servers,
            paths: ($this->paths ?? PathsObject::create())->with($path, $object),
            components: $this->components,
            security: $this->security,
            tags: $this->tags,
            externalDocs: $this->externalDocs,
            jsonSchemaDialect: $this->jsonSchemaDialect,
            openapi: $this->openapi,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        // `openapi` and `info` lead the document, as the specification presents them
        $members = ['openapi' => $this->openapi, 'info' => $this->info];
        foreach (get_object_vars($this) as $name => $member) {
            if ($member !== null && !array_key_exists((string) $name, $members)) {
                $members[(string) $name] = $member;
            }
        }
        return $members;
    }
}
