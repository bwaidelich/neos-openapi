<?php

declare(strict_types=1);

namespace Neos\OpenApi\Compilation;

use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\Schema as JsonSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;
use Neos\OpenApi\ApiDefinition;
use Neos\OpenApi\Attributes\AuthContext;
use Neos\OpenApi\Attributes\Operation;
use Neos\OpenApi\Attributes\Parameter;
use Neos\OpenApi\Attributes\RequestBody;
use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\TypeBindingProvider;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Dispatch\ArgumentBinding;
use Neos\OpenApi\Dispatch\ArgumentSource;
use Neos\OpenApi\Dispatch\ClassifiedArgument;
use Neos\OpenApi\Dispatch\DispatchEntry;
use Neos\OpenApi\Dispatch\DispatchTable;
use Neos\OpenApi\Exception\InvalidApiDefinitionException;
use Neos\OpenApi\Problem\ProblemDocument;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Response\ApiResponseWithHeaders;
use Neos\OpenApi\Response\StreamResponse;
use Neos\OpenApi\Response\TypedStreamResponse;
use Neos\OpenApi\Spec\ComponentsObject;
use Neos\OpenApi\Spec\HeaderObject;
use Neos\OpenApi\Spec\HeaderOrReferenceObjectMap;
use Neos\OpenApi\Spec\MediaTypeObject;
use Neos\OpenApi\Spec\MediaTypeObjectMap;
use Neos\OpenApi\Spec\OpenApiObject;
use Neos\OpenApi\Spec\OperationObject;
use Neos\OpenApi\Spec\ParameterObject;
use Neos\OpenApi\Spec\ParameterOrReferenceObjects;
use Neos\OpenApi\Spec\PathObject;
use Neos\OpenApi\Spec\PathsObject;
use Neos\OpenApi\Spec\RequestBodyObject;
use Neos\OpenApi\Spec\ResponseObject;
use Neos\OpenApi\Spec\ResponsesObject;
use Neos\OpenApi\Spec\SpecVersion;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;
use Neos\OpenApi\Support\ParameterLocation;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * Turns an {@see ApiDefinition} into a {@see CompiledApi}: the document to publish and the table to serve from,
 * built in one pass so the two cannot disagree.
 *
 * **This is the only place reflection happens.** Everything it produces is plain data, so a compiled API can be
 * cached and served without reflecting anything.
 *
 * Everything it can check, it checks here and fails loudly — a duplicated `operationId`, two operations claiming
 * one path and method, an argument nothing can fill, an auth context on an unsecured operation. All of those are
 * mistakes in the code being described, and finding them at compile time beats a confusing response later.
 */
final readonly class ApiCompiler
{
    public function __construct(
        private TypeBindingProvider $bindings,
    ) {}

    public function compile(ApiDefinition $api): CompiledApi
    {
        $components = SchemaComponents::create();
        $paths = PathsObject::create();
        $dispatchTable = DispatchTable::create();
        /** @var array<string, string> $operationIds operationId => where it was first seen */
        $operationIds = [];
        // whether any operation needed a 3.2-only field (`itemSchema`)
        $requiresItemSchema = false;

        foreach ($api->apiClasses as $registered) {
            $reflectionClass = new \ReflectionClass($registered->className);
            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attribute = $method->getAttributes(Operation::class)[0] ?? null;
                if ($attribute === null) {
                    continue;
                }
                $operation = $attribute->newInstance();

                $operationId = $operation->operationId ?? $method->getName();
                $origin = $registered->className . '::' . $method->getName() . '()';
                if (isset($operationIds[$operationId])) {
                    throw new InvalidApiDefinitionException(sprintf(
                        'The operationId "%s" is used by both %s and %s. It has to be unique across the document, '
                        . 'since clients generated from it turn operationIds into method names.',
                        $operationId,
                        $operationIds[$operationId],
                        $origin,
                    ), 1783500320);
                }
                $operationIds[$operationId] = $origin;

                if ($dispatchTable->has($operation->path, $operation->method)) {
                    throw new InvalidApiDefinitionException(sprintf(
                        'Two operations claim "%s %s"; the second is %s.',
                        $operation->method->value,
                        $operation->path->value,
                        $origin,
                    ), 1783500321);
                }

                $arguments = $this->classify($api, $registered, $method, $operation);
                $branches = $this->returnBranches($method);
                foreach ($branches['apiResponses'] as $responseClassName) {
                    $requiresItemSchema = $requiresItemSchema || is_a($responseClassName, TypedStreamResponse::class, true);
                }
                $compiled = $this->compileOperation($api, $registered, $operation, $operationId, $arguments, $branches, $components);
                $existing = $paths->get($operation->path);
                $paths = $existing === null
                    ? $paths->with($operation->path, PathObject::create()->withOperation($operation->method, $compiled))
                    : $paths->replace($operation->path, $existing->withOperation($operation->method, $compiled));

                $dispatchTable = $dispatchTable->with($operation->path, $operation->method, new DispatchEntry(
                    $registered->className,
                    $method->getName(),
                    array_map(static fn(ClassifiedArgument $a): ArgumentBinding => $a->binding, $arguments),
                    $operationId,
                    $branches['success'],
                ));
            }
        }

        $componentsObject = new ComponentsObject(
            schemas: $components->isEmpty() ? null : $components->toSchemaObjectMap(),
            securitySchemes: $api->securitySchemes,
        );
        $tags = $api->tags();

        return new CompiledApi(
            new OpenApiObject(
                info: $api->info,
                servers: $api->servers,
                paths: $paths->isEmpty() ? null : $paths,
                components: $componentsObject->isEmpty() ? null : $componentsObject,
                security: $api->security,
                tags: $tags->isEmpty() ? null : $tags,
                externalDocs: $api->externalDocs,
                openapi: $requiresItemSchema ? SpecVersion::ITEM_SCHEMA_VALUE : null,
            ),
            $dispatchTable,
        );
    }

    /**
     * @param list<ClassifiedArgument> $arguments
     * @param array{apiResponses: list<class-string<ApiResponse>>, success: TypeReference|null, empty: bool} $branches
     */
    private function compileOperation(
        ApiDefinition $api,
        RegisteredApiClass $registered,
        Operation $operation,
        string $operationId,
        array $arguments,
        array $branches,
        SchemaComponents $components,
    ): OperationObject {
        $parameters = [];
        $requestBody = null;
        foreach ($arguments as $classified) {
            if ($classified->source === ArgumentSource::authContext) {
                // the caller's identity is not part of the request's public shape
                continue;
            }
            if ($classified->source === ArgumentSource::body) {
                $requestBody = new RequestBodyObject(
                    content: MediaTypeObjectMap::create()->with(
                        MediaTypeRange::fromString($classified->contentType ?? 'application/json'),
                        new MediaTypeObject(schema: $this->bindings->for($classified->binding->type)->jsonSchema($components)),
                    ),
                    description: $classified->description,
                    required: $classified->binding->required,
                );
                continue;
            }
            $parameters[] = new ParameterObject(
                name: $classified->binding->wireName,
                in: self::locationOf($classified->source),
                description: $classified->description,
                required: $classified->binding->required ?: null,
                deprecated: $classified->deprecated,
                schema: $this->bindings->for($classified->binding->type)->jsonSchema($components),
            );
        }

        $responses = $this->compileResponses($branches, $components);
        if (($parameters !== [] || $requestBody !== null) && !$responses->hasResponseForStatusCode(HttpStatusCode::fromInteger(400))) {
            $responses = $responses->with(HttpStatusCode::fromInteger(400), new ResponseObject(
                description: 'The request could not be understood',
                content: self::problemContent($components),
            ));
        }
        // an operation the caller has to authenticate for is one the runtime will reject unauthenticated, so the
        // document says so — with the same payload the handler emits. An alternative allowing anonymous access
        // has nothing to reject.
        $security = $operation->security ?? $api->security;
        if ($security !== null && !$security->anonymousAccessAllowed && !$responses->hasResponseForStatusCode(HttpStatusCode::fromInteger(401))) {
            $responses = $responses->with(HttpStatusCode::fromInteger(401), new ResponseObject(
                description: 'The request was not authenticated',
                content: self::problemContent($components),
            ));
        }

        return new OperationObject(
            tags: [$registered->tag],
            summary: $operation->summary,
            description: $operation->description,
            operationId: $operationId,
            parameters: $parameters === [] ? null : new ParameterOrReferenceObjects(...$parameters),
            requestBody: $requestBody,
            responses: $responses->isEmpty() ? null : $responses,
            deprecated: $operation->deprecated,
            security: $operation->security,
        );
    }

    /**
     * The `content` of a response carrying a {@see ProblemDocument} — the payload the request handler emits,
     * described by the very class that emits it.
     */
    private static function problemContent(SchemaComponents $components): MediaTypeObjectMap
    {
        return MediaTypeObjectMap::create()->with(
            ProblemDocument::contentType(),
            new MediaTypeObject(schema: $components->register('ProblemDocument', ProblemDocument::class, ProblemDocument::schema())),
        );
    }

    /**
     * Decides, for every argument, where its value comes from.
     *
     * In order: `#[AuthContext]` → `#[RequestBody]` → `#[Parameter]` → named in the path template ⇒ path →
     * otherwise query. An argument that reaches the end on a method that *could* carry a body is an error rather
     * than a guess: the predecessor silently treated the first such argument as the body, so reordering a
     * signature changed the published API.
     *
     * @return list<ClassifiedArgument>
     */
    private function classify(
        ApiDefinition $api,
        RegisteredApiClass $registered,
        ReflectionMethod $method,
        Operation $operation,
    ): array {
        $where = sprintf('%s::%s()', $registered->className, $method->getName());
        $classified = [];
        $bodySeen = false;
        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $required = !$parameter->isOptional();

            if ($parameter->getAttributes(AuthContext::class) !== []) {
                $requirement = $operation->security ?? $api->security;
                if ($requirement === null) {
                    throw new InvalidApiDefinitionException(sprintf(
                        'The argument "$%s" of %s is marked #[AuthContext], but the operation requires no '
                        . 'authentication, so there would be no caller to hand over.',
                        $name,
                        $where,
                    ), 1783500322);
                }
                $type = $this->typeOf($parameter, $where);
                if ($requirement->anonymousAccessAllowed && !$type->nullable) {
                    throw new InvalidApiDefinitionException(sprintf(
                        'The argument "$%s" of %s is marked #[AuthContext] on an operation that also allows '
                        . 'anonymous access, so it has to be nullable — there may be no caller to hand over.',
                        $name,
                        $where,
                    ), 1783500334);
                }
                $classified[] = new ClassifiedArgument(
                    ArgumentBinding::authContext($name, $type),
                    ArgumentSource::authContext,
                );
                continue;
            }

            $bodyAttribute = $parameter->getAttributes(RequestBody::class)[0] ?? null;
            if ($bodyAttribute !== null) {
                if ($bodySeen) {
                    throw new InvalidApiDefinitionException(sprintf('%s declares more than one #[RequestBody] argument', $where), 1783500323);
                }
                if (!$operation->method->allowsRequestBody()) {
                    throw new InvalidApiDefinitionException(sprintf(
                        '%s declares a #[RequestBody] argument, but a %s request is not expected to carry one',
                        $where,
                        $operation->method->value,
                    ), 1783500324);
                }
                $bodySeen = true;
                $instance = $bodyAttribute->newInstance();
                $classified[] = new ClassifiedArgument(
                    ArgumentBinding::body($name, $this->typeOf($parameter, $where), $required),
                    ArgumentSource::body,
                    $instance->description,
                    null,
                    $instance->contentType,
                );
                continue;
            }

            $parameterAttribute = $parameter->getAttributes(Parameter::class)[0] ?? null;
            if ($parameterAttribute !== null) {
                $instance = $parameterAttribute->newInstance();
                $source = ArgumentSource::from($instance->in->value);
                if ($instance->in === ParameterLocation::path && !$operation->path->containsPlaceholder($name)) {
                    throw new InvalidApiDefinitionException(sprintf(
                        'The argument "$%s" of %s is declared a path parameter, but "%s" has no {%s} placeholder',
                        $name,
                        $where,
                        $operation->path->value,
                        $name,
                    ), 1783500325);
                }
                $classified[] = new ClassifiedArgument(
                    ArgumentBinding::fromRequest($name, $this->typeOf($parameter, $where), $source, $instance->name ?? $name, $source === ArgumentSource::path ? true : $required),
                    $source,
                    $instance->description,
                    $instance->deprecated,
                );
                continue;
            }

            if ($operation->path->containsPlaceholder($name)) {
                if (!$required) {
                    throw new InvalidApiDefinitionException(sprintf(
                        'The path parameter "$%s" of %s cannot be optional — it is part of the path',
                        $name,
                        $where,
                    ), 1783500326);
                }
                $classified[] = new ClassifiedArgument(
                    ArgumentBinding::fromRequest($name, $this->typeOf($parameter, $where), ArgumentSource::path, $name, true),
                    ArgumentSource::path,
                );
                continue;
            }

            if ($operation->method->allowsRequestBody()) {
                throw new InvalidApiDefinitionException(sprintf(
                    'The argument "$%s" of %s is not accounted for: on a %s operation it needs #[RequestBody] if it '
                    . 'is the body, or #[Parameter] if it is not. It is deliberately not inferred.',
                    $name,
                    $where,
                    $operation->method->value,
                ), 1783500327);
            }

            $classified[] = new ClassifiedArgument(
                ArgumentBinding::fromRequest($name, $this->typeOf($parameter, $where), ArgumentSource::query, $name, $required),
                ArgumentSource::query,
            );
        }
        return $classified;
    }

    /**
     * Splits a return type into the responses it declares.
     *
     * At most *one* branch may be an ordinary type: each would become a `200`, so a second would silently
     * overwrite the first and the document would describe only whichever came last. The declared type is also what
     * the runtime serializes through — not the returned value's own class, since a polymorphic return has to go
     * out as its union to carry the discriminator tag.
     *
     * @return array{apiResponses: list<class-string<ApiResponse>>, success: TypeReference|null, empty: bool}
     */
    private function returnBranches(ReflectionMethod $method): array
    {
        $where = sprintf('%s::%s()', $method->getDeclaringClass()->getName(), $method->getName());
        $returnType = $method->getReturnType();
        if ($returnType === null) {
            throw new InvalidApiDefinitionException(sprintf('%s has no return type, so its responses cannot be described', $where), 1783500328);
        }
        $apiResponses = [];
        $success = null;
        $empty = false;
        $types = $returnType instanceof ReflectionUnionType ? $returnType->getTypes() : [$returnType];
        foreach ($types as $type) {
            if (!$type instanceof ReflectionNamedType) {
                throw new InvalidApiDefinitionException(sprintf(
                    'The return type of %s is not supported (only named types and unions of them are)',
                    $where,
                ), 1783500329);
            }
            $name = $type->getName();
            if ($name === 'void' || $name === 'null') {
                $empty = true;
                continue;
            }
            if (!$type->isBuiltin() && is_a($name, ApiResponse::class, true)) {
                /** @var class-string<ApiResponse> $name */
                $apiResponses[] = $name;
                continue;
            }
            if ($success !== null) {
                throw new InvalidApiDefinitionException(sprintf(
                    'The return type of %s has more than one ordinary branch ("%s" and "%s"). Each would be a 200, '
                    . 'so only the last would survive — give the others an ApiResponse type.',
                    $where,
                    $success->describe(),
                    $name,
                ), 1783500333);
            }
            $success = $this->namedTypeToReference($type, sprintf('the return type of %s', $where));
        }
        return ['apiResponses' => $apiResponses, 'success' => $success, 'empty' => $empty];
    }

    /**
     * @param array{apiResponses: list<class-string<ApiResponse>>, success: TypeReference|null, empty: bool} $branches
     */
    private function compileResponses(array $branches, SchemaComponents $components): ResponsesObject
    {
        $responses = ResponsesObject::create();
        foreach ($branches['apiResponses'] as $responseClassName) {
            $responses = $responses->with($responseClassName::statusCode(), $this->apiResponse($responseClassName, $components));
        }
        if ($branches['success'] !== null) {
            $responses = $responses->with(HttpStatusCode::fromInteger(200), new ResponseObject(
                description: 'OK',
                content: MediaTypeObjectMap::json(new MediaTypeObject(
                    schema: $this->bindings->for($branches['success'])->jsonSchema($components),
                )),
            ));
        }
        // a `void` branch is a response the runtime really does emit, so the document has to say so — the
        // handler answers it with a bodyless 204
        if ($branches['empty'] && !$responses->hasResponseForStatusCode(HttpStatusCode::fromInteger(204))) {
            $responses = $responses->with(HttpStatusCode::fromInteger(204), new ResponseObject(description: 'No Content'));
        }
        return $responses;
    }

    /**
     * @param class-string<ApiResponse> $responseClassName
     */
    private function apiResponse(string $responseClassName, SchemaComponents $components): ResponseObject
    {
        if (is_a($responseClassName, StreamResponse::class, true)) {
            /** @var class-string<StreamResponse> $responseClassName */
            return $this->streamResponse($responseClassName, $components);
        }
        $bodyType = $responseClassName::bodyType();
        $content = null;
        if ($bodyType !== null) {
            $contentType = $responseClassName::contentType() ?? MediaTypeRange::fromString('application/json');
            $content = MediaTypeObjectMap::create()->with(
                $contentType,
                new MediaTypeObject(schema: $this->bindings->for($bodyType)->jsonSchema($components)),
            );
        }
        return new ResponseObject(
            description: $responseClassName::description(),
            // headers are not tied to a body: a bodyless 204 may still carry a Location
            headers: $this->responseHeaders($responseClassName, $components),
            content: $content,
        );
    }

    /**
     * A {@see StreamResponse}'s body is a sequence, not one value — there is no `bodyType()` to build a `schema`
     * from. A {@see TypedStreamResponse} instead describes its items via `itemSchema`, shaped as the OpenAPI
     * registry's own SSE examples are: `data`, `event`, `id` and `retry` as properties of one object, with `data`
     * narrowed to the declared item type. Whether a document needs `itemSchema` at all — and so has to advertise
     * OpenAPI 3.2 rather than 3.1.1 — is decided independently, in {@see self::compile()}, by asking the same
     * question of every response branch it already has in hand; nothing here needs to report it back.
     *
     * @param class-string<StreamResponse> $responseClassName
     * @see https://spec.openapis.org/registry/media-type/sse
     */
    private function streamResponse(string $responseClassName, SchemaComponents $components): ResponseObject
    {
        $mediaType = is_a($responseClassName, TypedStreamResponse::class, true)
            ? new MediaTypeObject(itemSchema: $this->sseItemSchema($responseClassName::itemType(), $components))
            : new MediaTypeObject();
        return new ResponseObject(
            description: $responseClassName::description(),
            headers: $this->responseHeaders($responseClassName, $components),
            content: MediaTypeObjectMap::create()->with($responseClassName::contentType(), $mediaType),
        );
    }

    private function sseItemSchema(TypeReference $itemType, SchemaComponents $components): JsonSchema
    {
        return ObjectSchema::create(
            properties: ObjectProperties::create(
                data: $this->bindings->for($itemType)->jsonSchema($components),
                event: StringSchema::create(description: 'The event name'),
                id: StringSchema::create(description: 'The event ID'),
                retry: IntegerSchema::create(description: 'The reconnection time in milliseconds', minimum: 0),
            ),
            required: ['data'],
        );
    }

    /**
     * The declared headers of a response, each with the schema of the type it declared — resolved here because
     * this is where the {@see TypeBindingProvider} and the {@see SchemaComponents} are.
     *
     * @param class-string<ApiResponse> $responseClassName
     */
    private function responseHeaders(string $responseClassName, SchemaComponents $components): HeaderOrReferenceObjectMap|null
    {
        if (!is_a($responseClassName, ApiResponseWithHeaders::class, true)) {
            return null;
        }
        $declared = $responseClassName::headerTypes();
        if ($declared->isEmpty()) {
            return null;
        }
        $headers = HeaderOrReferenceObjectMap::create();
        foreach ($declared as $header) {
            $headers = $headers->with($header->name, new HeaderObject(
                description: $header->description,
                required: $header->required ?: null,
                deprecated: $header->deprecated ?: null,
                schema: $this->bindings->for($header->type)->jsonSchema($components),
            ));
        }
        return $headers;
    }

    private function typeOf(ReflectionParameter $parameter, string $where): TypeReference
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType) {
            throw new InvalidApiDefinitionException(sprintf(
                'The argument "$%s" of %s must have a single named type',
                $parameter->getName(),
                $where,
            ), 1783500330);
        }
        return $this->namedTypeToReference($type, sprintf('the argument "$%s" of %s', $parameter->getName(), $where));
    }

    private function namedTypeToReference(ReflectionNamedType $type, string $what): TypeReference
    {
        $name = $type->getName();
        if (!$type->isBuiltin()) {
            /** @var class-string $name */
            return TypeReference::of($name, $type->allowsNull());
        }
        $builtin = BuiltinType::tryFrom($name);
        if ($builtin === null) {
            throw new InvalidApiDefinitionException(sprintf('The type "%s" of %s is not supported', $name, $what), 1783500331);
        }
        return TypeReference::builtin($builtin, $type->allowsNull());
    }

    private static function locationOf(ArgumentSource $source): ParameterLocation
    {
        return match ($source) {
            ArgumentSource::path => ParameterLocation::path,
            ArgumentSource::query => ParameterLocation::query,
            ArgumentSource::header => ParameterLocation::header,
            ArgumentSource::cookie => ParameterLocation::cookie,
            ArgumentSource::body, ArgumentSource::authContext => throw new \LogicException(
                sprintf('"%s" is not a parameter location', $source->value),
                1783500332,
            ),
        };
    }
}
