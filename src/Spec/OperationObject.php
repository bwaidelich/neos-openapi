<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * One HTTP operation.
 *
 * Carries no runtime metadata: the method to call and the binding of each argument live in the Dispatch Table, so
 * this object is nothing but the specification (ADR 0003). The predecessor smuggled `methodName`, `parameterName`
 * and a coercion schema through a `meta` array here.
 *
 * @see https://spec.openapis.org/oas/v3.1.1#operation-object
 */
final readonly class OperationObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    /**
     * @param list<string>|null $tags
     */
    public function __construct(
        public array|null $tags = null,
        public string|null $summary = null,
        public string|null $description = null,
        public ExternalDocumentationObject|null $externalDocs = null,
        public string|null $operationId = null,
        public ParameterOrReferenceObjects|null $parameters = null,
        public RequestBodyObject|ReferenceObject|null $requestBody = null,
        public ResponsesObject|null $responses = null,
        public bool|null $deprecated = null,
        public SecurityRequirementObject|null $security = null,
        public ServerObjects|null $servers = null,
    ) {}
}
