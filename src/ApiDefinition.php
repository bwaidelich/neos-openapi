<?php

declare(strict_types=1);

namespace Neos\OpenApi;

use Neos\OpenApi\Compilation\ApiCompiler;
use Neos\OpenApi\Compilation\CompiledApi;
use Neos\OpenApi\Compilation\RegisteredApiClass;
use Neos\OpenApi\Spec\ExternalDocumentationObject;
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Spec\SecurityRequirementObject;
use Neos\OpenApi\Spec\SecuritySchemeOrReferenceObjectMap;
use Neos\OpenApi\Spec\ServerObjects;
use Neos\OpenApi\Spec\TagObject;
use Neos\OpenApi\Spec\TagObjects;

/**
 * The single description of one API: its global configuration *plus* the Api Classes whose methods make it up.
 *
 * Global configuration lives here rather than on the classes — which is what lets one API span as many of them as
 * it likes, and why there is no class-level attribute. It replaces both the predecessor's `#[OpenApi]` attribute
 * and its `OpenApiGeneratorOptions`, two things that always travelled together.
 *
 * This is a *definition*, not a document: {@see ApiCompiler} turns it into a {@see CompiledApi}.
 */
final readonly class ApiDefinition
{
    /**
     * @param list<RegisteredApiClass> $apiClasses
     */
    private function __construct(
        public InfoObject $info,
        public ServerObjects|null $servers,
        public SecuritySchemeOrReferenceObjectMap|null $securitySchemes,
        public SecurityRequirementObject|null $security,
        public ExternalDocumentationObject|null $externalDocs,
        public array $apiClasses,
    ) {}

    public static function create(
        InfoObject $info,
        ServerObjects|null $servers = null,
        SecuritySchemeOrReferenceObjectMap|null $securitySchemes = null,
        SecurityRequirementObject|null $security = null,
        ExternalDocumentationObject|null $externalDocs = null,
    ): self {
        return new self($info, $servers, $securitySchemes, $security, $externalDocs, []);
    }

    /**
     * Registers an Api Class, tagging its operations (by default with the class's short name).
     *
     * @param class-string $className
     */
    public function withOperationsFrom(string $className, string|null $tag = null, string|null $tagDescription = null): self
    {
        foreach ($this->apiClasses as $registered) {
            if ($registered->className === $className) {
                throw new \InvalidArgumentException(sprintf('The Api Class "%s" is already registered', $className), 1783500310);
            }
        }
        return new self(
            $this->info,
            $this->servers,
            $this->securitySchemes,
            $this->security,
            $this->externalDocs,
            [...$this->apiClasses, RegisteredApiClass::create($className, $tag, $tagDescription)],
        );
    }

    /**
     * The tags of every registered class, in registration order — the document's `tags` member.
     */
    public function tags(): TagObjects
    {
        $tags = [];
        foreach ($this->apiClasses as $registered) {
            $tags[$registered->tag] ??= new TagObject($registered->tag, $registered->tagDescription);
        }
        return new TagObjects(...array_values($tags));
    }
}
