<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\HttpMethod;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * The operations available on one path — what the specification calls a Path Item Object.
 *
 * @see https://spec.openapis.org/oas/v3.1.1#path-item-object
 */
final readonly class PathObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public string|null $summary = null,
        public string|null $description = null,
        public OperationObject|null $get = null,
        public OperationObject|null $put = null,
        public OperationObject|null $post = null,
        public OperationObject|null $delete = null,
        public OperationObject|null $options = null,
        public OperationObject|null $head = null,
        public OperationObject|null $patch = null,
        public OperationObject|null $trace = null,
        public ServerObjects|null $servers = null,
        public ParameterOrReferenceObjects|null $parameters = null,
    ) {}

    public static function create(): self
    {
        return new self();
    }

    public function operation(HttpMethod $method): OperationObject|null
    {
        return match ($method) {
            HttpMethod::GET => $this->get,
            HttpMethod::PUT => $this->put,
            HttpMethod::POST => $this->post,
            HttpMethod::DELETE => $this->delete,
            HttpMethod::OPTIONS => $this->options,
            HttpMethod::HEAD => $this->head,
            HttpMethod::PATCH => $this->patch,
            HttpMethod::TRACE => $this->trace,
        };
    }

    public function withOperation(HttpMethod $method, OperationObject $operation): self
    {
        $operations = [];
        foreach (HttpMethod::cases() as $case) {
            $operations[$case->specMember()] = $case === $method ? $operation : $this->operation($case);
        }
        return new self(
            summary: $this->summary,
            description: $this->description,
            get: $operations['get'],
            put: $operations['put'],
            post: $operations['post'],
            delete: $operations['delete'],
            options: $operations['options'],
            head: $operations['head'],
            patch: $operations['patch'],
            trace: $operations['trace'],
            servers: $this->servers,
            parameters: $this->parameters,
        );
    }

    /**
     * The methods this path actually answers, in specification order.
     *
     * @return iterable<string, OperationObject> the lowercase member name => the operation
     */
    public function operations(): iterable
    {
        foreach (HttpMethod::cases() as $method) {
            $operation = $this->operation($method);
            if ($operation !== null) {
                yield $method->specMember() => $operation;
            }
        }
    }

    /**
     * @return list<HttpMethod>
     */
    public function allowedMethods(): array
    {
        $methods = [];
        foreach (HttpMethod::cases() as $method) {
            if ($this->operation($method) !== null) {
                $methods[] = $method;
            }
        }
        return $methods;
    }
}
