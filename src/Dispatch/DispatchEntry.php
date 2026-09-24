<?php

declare(strict_types=1);

namespace Neos\OpenApi\Dispatch;

use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Support\HttpMethod;
use Neos\OpenApi\Support\RelativePath;

/**
 * Everything needed to *serve* one operation: where it lives (path template and HTTP method), the operationId it is
 * known by, which method of which class to call, and how to fill its arguments.
 *
 * The runtime counterpart of an Operation Object, kept beside the document rather than inside it — the
 * predecessor smuggled this through `meta` arrays on the spec objects.
 */
final readonly class DispatchEntry
{
    /**
     * @param string $operationId unique across the document — declared on the attribute, or the method's name
     * @param class-string $apiClassName
     * @param list<ArgumentBinding> $arguments
     * @param list<TypeReference> $successTypes the *declared* types a successful result may have — empty for
     *                                          `void`, one for an ordinary return type, several for a union,
     *                                          in the order they were declared.
     */
    public function __construct(
        public RelativePath $path,
        public HttpMethod $method,
        public string $operationId,
        public string $apiClassName,
        public string $methodName,
        public array $arguments,
        public array $successTypes = [],
    ) {}
}
