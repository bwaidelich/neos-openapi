<?php

declare(strict_types=1);

namespace Neos\OpenApi\Http;

use JsonException;
use Neos\JsonSchema\Validation\Issue;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\JsonSchema\Validation\Issues;
use Neos\OpenApi\Binding\TypeBindingProvider;
use Neos\OpenApi\Compilation\CompiledApi;
use Neos\OpenApi\Dispatch\ArgumentBinding;
use Neos\OpenApi\Dispatch\ArgumentSource;
use Neos\OpenApi\Dispatch\DispatchEntry;
use Neos\OpenApi\Problem\ProblemDocument;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Response\ApiResponseWithHeaders;
use Neos\OpenApi\Response\ResponseHeader;
use Neos\OpenApi\Response\SseEvent;
use Neos\OpenApi\Response\StreamResponse;
use Neos\OpenApi\Spec\SecurityRequirementObject;
use Neos\OpenApi\Spec\SecuritySchemeObject;
use Neos\OpenApi\Support\HttpMethod;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;
use Neos\OpenApi\Support\SecuritySchemeType;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serves a {@see CompiledApi} over PSR-7.
 *
 * **No reflection happens here.** The document routes the request to a path template, the Dispatch Table says
 * which method of which Api Class answers it and where each of its arguments comes from, and the {@see
 * TypeBindingProvider} — the same one that described those types when the document was generated — turns request
 * data into them and the result back into a payload. That shared provider is the whole reason a response cannot
 * contradict the document that advertised it.
 *
 * What it answers itself, and what it does not:
 *
 * - `404`, `405`, `400` and `401` are this handler's own answers, all as {@see ProblemDocument}s, and every one of
 *   them except the two routing failures is a response the compiler put in the document.
 * - Everything an operation throws travels on untouched. Turning a domain exception into a status code is an
 *   application's decision, and a middleware around this handler is where it belongs — inventing a `500` payload
 *   the document never mentioned would be exactly the drift this package exists to prevent.
 */
final readonly class RequestHandler implements RequestHandlerInterface
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    public function __construct(
        private CompiledApi $api,
        private TypeBindingProvider $bindings,
        private ApiClassResolver $apiClasses,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private AuthContextProvider|null $authContexts = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $paths = $this->api->document->paths;
        $variables = [];
        $template = $paths?->matchTemplate($path, $variables);
        if ($paths === null || $template === null) {
            return $this->problem(404, 'Not Found', sprintf('No operation is available at "%s"', $path));
        }
        $pathObject = $paths->get($template);
        if ($pathObject === null) {
            throw new \LogicException(sprintf('The path "%s" matched but is not in the document', $template->value), 1783500410);
        }
        $method = HttpMethod::tryFrom(strtoupper($request->getMethod()));
        $operation = $method === null ? null : $pathObject->operation($method);
        if ($method === null || $operation === null) {
            $allowed = array_map(static fn(HttpMethod $allowed): string => $allowed->value, $pathObject->allowedMethods());
            return $this->problem(405, 'Method Not Allowed', sprintf(
                '"%s" does not answer %s requests',
                $template->value,
                strtoupper($request->getMethod()),
            ))->withHeader('Allow', implode(', ', $allowed));
        }
        $entry = $this->api->dispatchTable->find($template, $method);
        if ($entry === null) {
            throw new \LogicException(sprintf(
                'The document describes "%s %s" but the Dispatch Table has no entry for it',
                $method->value,
                $template->value,
            ), 1783500411);
        }

        $requirement = $operation->security ?? $this->api->document->security;
        $authContext = null;
        if ($requirement !== null) {
            if ($this->authContexts === null) {
                throw new \LogicException(sprintf(
                    'The operation "%s" requires authentication, but this handler was built without an %s',
                    $entry->operationId ?? $template->value,
                    AuthContextProvider::class,
                ), 1783500412);
            }
            $authContext = $this->authContexts->authContextFor($request, $requirement);
            if ($authContext === null && !$requirement->anonymousAccessAllowed) {
                return $this->unauthorized($requirement);
            }
        }

        $filled = $this->arguments($entry, $request, $variables ?? [], $authContext);
        if ($filled['rejection'] !== null) {
            return $filled['rejection'];
        }
        if (!$filled['issues']->isEmpty()) {
            return $this->problem(400, 'Bad Request', 'The request could not be understood', $filled['issues']);
        }

        return $this->respond($entry, $this->invoke($entry, $filled['arguments']));
    }

    /**
     * Fills every argument of an operation from the request, collecting *all* the reasons it could not be filled
     * rather than stopping at the first — a caller fixing three mistakes should need one round trip, not three.
     *
     * `rejection` is for the failures that are about the *request* rather than about one value, and so have no
     * issue to report: a body that is not JSON at all has no path to blame it on.
     *
     * @param array<string, string> $variables the path template's variables, as matched
     * @return array{arguments: array<string, mixed>, issues: Issues, rejection: ResponseInterface|null} the
     *         arguments ready to be spread as named arguments, and why any of them are missing
     */
    private function arguments(
        DispatchEntry $entry,
        ServerRequestInterface $request,
        array $variables,
        object|null $authContext,
    ): array {
        $issues = Issues::create();
        $arguments = [];
        foreach ($entry->arguments as $binding) {
            if ($binding->source === ArgumentSource::authContext) {
                $arguments[$binding->argumentName] = $authContext;
                continue;
            }
            if ($binding->source === ArgumentSource::body) {
                $body = (string) $request->getBody();
                if (trim($body) === '') {
                    if ($binding->required) {
                        $issues = $issues->withAppended(Issue::create(['body'], IssueCode::Required, 'A request body is required'));
                    }
                    continue;
                }
                $contentType = $request->getHeaderLine('Content-Type');
                if ($contentType !== '' && !self::isJson($contentType)) {
                    // a 400 rather than the arguably more precise 415, because the document advertises a 400 for
                    // this operation and nothing else
                    return [
                        'arguments' => $arguments,
                        'issues' => $issues,
                        'rejection' => $this->problem(400, 'Bad Request', sprintf(
                            'Only JSON request bodies are supported, got "%s"',
                            $contentType,
                        )),
                    ];
                }
                try {
                    $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    return [
                        'arguments' => $arguments,
                        'issues' => $issues,
                        'rejection' => $this->problem(400, 'Bad Request', 'The request body is not valid JSON: ' . $exception->getMessage()),
                    ];
                }
                $outcome = $this->bindings->for($binding->type)->coerce($decoded);
                if (!$outcome->success) {
                    $issues = $issues->merge(self::prefixed($outcome->issues, ['body']));
                    continue;
                }
                $arguments[$binding->argumentName] = $outcome->value();
                continue;
            }
            $raw = $this->rawParameter($binding, $request, $variables);
            if ($raw === null) {
                if ($binding->required) {
                    $issues = $issues->withAppended(Issue::create(
                        [$binding->source->value, $binding->wireName],
                        IssueCode::Required,
                        sprintf('The %s parameter "%s" is required', $binding->source->value, $binding->wireName),
                    ));
                }
                // absent and optional: leave it out, so the method's own default value applies
                continue;
            }
            $outcome = $this->bindings->for($binding->type)->coerce($raw);
            if (!$outcome->success) {
                $issues = $issues->merge(self::prefixed($outcome->issues, [$binding->source->value, $binding->wireName]));
                continue;
            }
            $arguments[$binding->argumentName] = $outcome->value();
        }
        return ['arguments' => $arguments, 'issues' => $issues, 'rejection' => null];
    }

    /**
     * @param array<string, string> $variables
     */
    private function rawParameter(ArgumentBinding $binding, ServerRequestInterface $request, array $variables): mixed
    {
        return match ($binding->source) {
            // a path variable arrives percent-encoded, since it is a *URI* segment
            ArgumentSource::path => isset($variables[$binding->wireName]) ? rawurldecode($variables[$binding->wireName]) : null,
            ArgumentSource::query => $request->getQueryParams()[$binding->wireName] ?? null,
            ArgumentSource::header => $request->hasHeader($binding->wireName) ? $request->getHeaderLine($binding->wireName) : null,
            ArgumentSource::cookie => $request->getCookieParams()[$binding->wireName] ?? null,
            ArgumentSource::body, ArgumentSource::authContext => throw new \LogicException(
                sprintf('"%s" is not a request parameter', $binding->source->value),
                1783500413,
            ),
        };
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function invoke(DispatchEntry $entry, array $arguments): mixed
    {
        $apiClass = $this->apiClasses->resolve($entry->apiClassName);
        $callable = [$apiClass, $entry->methodName];
        if (!is_callable($callable)) {
            throw new \LogicException(sprintf(
                'The Api Class %s has no public method "%s"',
                $apiClass::class,
                $entry->methodName,
            ), 1783500414);
        }
        // named arguments, so an absent optional one falls back to the method's own default
        return $callable(...$arguments);
    }

    private function respond(DispatchEntry $entry, mixed $result): ResponseInterface
    {
        if ($result instanceof StreamResponse) {
            return $this->respondStream($result);
        }
        if ($result instanceof ApiResponse) {
            $bodyType = $result::bodyType();
            if ($bodyType === null) {
                $response = $this->responseFactory->createResponse($result::statusCode()->value);
            } else {
                $contentType = $result::contentType() ?? MediaTypeRange::fromString('application/json');
                $response = $this->json(
                    $result::statusCode()->value,
                    $contentType->value,
                    $this->bindings->for($bodyType)->serialize($result->body()),
                );
            }
            return $result instanceof ApiResponseWithHeaders ? $this->withDeclaredHeaders($response, $result) : $response;
        }
        if ($entry->successType === null) {
            // the operation returns `void`, which the document describes as a 204
            return $this->responseFactory->createResponse(204);
        }
        // serialized through the binding the document's schema came from, never json_encode'd raw
        return $this->json(200, 'application/json', $this->bindings->for($entry->successType)->serialize($result));
    }

    /**
     * Unlike every other response, this one is never buffered into a string: {@see GeneratorStream} pulls chunks
     * from {@see StreamResponse::stream()} on demand, so the connection carries each one as it becomes available
     * rather than only once the whole thing exists.
     */
    private function respondStream(StreamResponse $result): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($result::statusCode()->value)
            ->withHeader('Content-Type', $result::contentType()->value)
            ->withBody(new GeneratorStream($this->streamChunks($result)));
        return $result instanceof ApiResponseWithHeaders ? $this->withDeclaredHeaders($response, $result) : $response;
    }

    /**
     * Normalises what a {@see StreamResponse} yields into wire bytes: a raw `string` passes through untouched, and
     * an {@see SseEvent} is rendered through the same {@see TypeBindingProvider} every other response body goes
     * through, so a typed event's data cannot drift from the `itemSchema` the document advertised for it.
     *
     * @return \Generator<int, string>
     */
    private function streamChunks(StreamResponse $result): \Generator
    {
        foreach ($result->stream() as $item) {
            yield $item instanceof SseEvent ? $item->render($this->bindings) : $item;
        }
    }

    /**
     * Writes the headers a response declared, and **only** those.
     *
     * Each value goes out through the binding its declared type names, exactly as a body does, so a header cannot
     * carry something the schema published for it does not describe. The three ways a response class can
     * contradict its own declaration — an undeclared name, a missing required value, a value that is not a field
     * value at all — are `LogicException`s rather than 4xx or 5xx responses: they mean the API is wrong, not the
     * request, and this handler invents no status the document does not mention.
     */
    private function withDeclaredHeaders(ResponseInterface $response, ApiResponseWithHeaders $result): ResponseInterface
    {
        $values = $result->headers();
        $declared = $result::headerTypes();
        foreach ($values as $name => $value) {
            if ($declared->get($name) === null) {
                throw new \LogicException(sprintf(
                    'The response %s returns a header "%s" it does not declare, so the document does not describe it',
                    $result::class,
                    $name,
                ), 1783500415);
            }
        }
        foreach ($declared as $header) {
            $value = self::headerValueOf($values, $header->name);
            // an empty *list* is a repeated header repeated no times, which is the same as not sending it — and
            // is not the same as an empty string, which is a value and does go out
            $field = $value === null ? [] : self::fieldValue($this->bindings->for($header->type)->serialize($value), $header, $result);
            if ($field === []) {
                if ($header->required) {
                    throw new \LogicException(sprintf(
                        'The response %s declares the header "%s" as required but returns no value for it',
                        $result::class,
                        $header->name,
                    ), 1783500416);
                }
                continue;
            }
            $response = $response->withHeader($header->name, $field);
        }
        return $response;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function headerValueOf(array $values, string $name): mixed
    {
        foreach ($values as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    /**
     * A serialized value as a field value: a scalar becomes one, a list becomes one field per element (which is
     * what a repeated header is), and anything else — an object, a map — has no field representation to fall back
     * on and says so.
     *
     * @return string|list<string>
     */
    private static function fieldValue(mixed $serialized, ResponseHeader $header, ApiResponseWithHeaders $result): string|array
    {
        if (is_scalar($serialized)) {
            return self::scalarFieldValue($serialized);
        }
        if (!is_array($serialized) || !array_is_list($serialized)) {
            self::unrenderable($serialized, $header, $result);
        }
        $values = [];
        foreach ($serialized as $element) {
            if (!is_scalar($element)) {
                self::unrenderable($serialized, $header, $result);
            }
            $values[] = self::scalarFieldValue($element);
        }
        return $values;
    }

    private static function scalarFieldValue(string|int|float|bool $value): string
    {
        // `true` would otherwise be "1" and `false` the empty string, neither of which reads as a boolean
        return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    }

    private static function unrenderable(mixed $serialized, ResponseHeader $header, ApiResponseWithHeaders $result): never
    {
        throw new \LogicException(sprintf(
            'The header "%s" of %s serializes to %s, which is not a header value — a header carries a scalar, or a '
            . 'list of them',
            $header->name,
            $result::class,
            get_debug_type($serialized),
        ), 1783500417);
    }

    /**
     * A `401` carrying the challenge the declared security scheme implies, as RFC 9110 requires of one.
     */
    private function unauthorized(SecurityRequirementObject $requirement): ResponseInterface
    {
        $response = $this->problem(401, 'Unauthorized', 'The request carries no credentials this operation accepts');
        $challenge = $this->challengeFor($requirement);
        return $challenge === null ? $response : $response->withHeader('WWW-Authenticate', $challenge);
    }

    private function challengeFor(SecurityRequirementObject $requirement): string|null
    {
        $schemes = $this->api->document->components?->securitySchemes;
        if ($schemes === null) {
            return null;
        }
        foreach ($requirement->schemeNames() as $name) {
            $scheme = $schemes->get($name);
            if (!$scheme instanceof SecuritySchemeObject) {
                continue;
            }
            $challenge = match ($scheme->type) {
                SecuritySchemeType::http => $this->httpChallenge((string) $scheme->scheme),
                SecuritySchemeType::oauth2, SecuritySchemeType::openIdConnect => 'Bearer',
                default => null,
            };
            if ($challenge !== null && $challenge !== '') {
                return $challenge;
            }
        }
        return null;
    }

    /**
     * An `http` scheme's challenge: its own name, and for Basic the `realm` that RFC 7617 wants with it.
     *
     * The realm is the document's `info.title`, because the Security Scheme Object has nowhere to carry one and
     * the protection space *is* the API this document describes. Everything the handler does still comes from the
     * document, which is the rule the rest of this class follows too.
     */
    private function httpChallenge(string $scheme): string
    {
        if ($scheme === '') {
            return '';
        }
        // scheme names are case-insensitive (RFC 9110), so `Basic` and `basic` are the same scheme
        if (strcasecmp($scheme, 'basic') !== 0) {
            return ucfirst($scheme);
        }
        return sprintf('Basic realm="%s"', self::quoted($this->api->document->info->title));
    }

    /**
     * A `quoted-string`'s content: `"` and `\` are the two characters that have to be escaped inside one.
     */
    private static function quoted(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function problem(int $status, string $title, string|null $detail = null, Issues|null $issues = null): ResponseInterface
    {
        $document = ProblemDocument::create(HttpStatusCode::fromInteger($status), $title, $detail, $issues);
        return $this->json($status, ProblemDocument::CONTENT_TYPE, $document);
    }

    private function json(int $status, string $contentType, mixed $body): ResponseInterface
    {
        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streamFactory->createStream((string) json_encode($body, self::JSON_FLAGS)));
    }

    /**
     * Locates an engine's issues within the request they came from — `name` inside a body becomes `/body/name`,
     * and a rejected query parameter becomes `/query/status`, so one flat list of issues says where each of them
     * belongs without the caller having to know which part failed.
     */
    /**
     * @param list<string> $prefix
     */
    private static function prefixed(Issues|null $issues, array $prefix): Issues
    {
        $prefixed = Issues::create();
        foreach ($issues?->toArray() ?? [] as $issue) {
            $prefixed = $prefixed->withAppended(Issue::create(array_merge($prefix, $issue->path), $issue->code, $issue->message));
        }
        return $prefixed;
    }

    private static function isJson(string $contentType): bool
    {
        $mediaType = trim(explode(';', $contentType, 2)[0]);
        return $mediaType === '' || str_ends_with($mediaType, '/json') || str_ends_with($mediaType, '+json');
    }
}
