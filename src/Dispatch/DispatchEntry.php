<?php

declare(strict_types=1);

namespace Neos\OpenApi\Dispatch;

use Neos\OpenApi\Binding\TypeReference;

/**
 * Everything needed to *serve* one operation: which method of which class to call, and how to fill its arguments.
 *
 * The runtime counterpart of an Operation Object, kept beside the document rather than inside it — the
 * predecessor smuggled this through `meta` arrays on the spec objects.
 */
final readonly class DispatchEntry
{
    /**
     * @param class-string $apiClassName
     * @param list<ArgumentBinding> $arguments
     * @param list<TypeReference> $successTypes the *declared* types a successful result may have — empty for
     *                                          `void`, one for an ordinary return type, several for a union,
     *                                          in the order they were declared.
     */
    public function __construct(
        public string $apiClassName,
        public string $methodName,
        public array $arguments,
        public string|null $operationId,
        public array $successTypes = [],
    ) {}
}
