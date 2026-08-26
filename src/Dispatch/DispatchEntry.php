<?php

declare(strict_types=1);

namespace Neos\OpenApi\Dispatch;

use Neos\OpenApi\Binding\TypeReference;

/**
 * Everything needed to *serve* one operation: which method of which class to call, and how to fill its arguments.
 *
 * The runtime counterpart of an Operation Object, kept beside the document rather than inside it (ADR 0003) —
 * the predecessor smuggled this through `meta` arrays on the spec objects.
 */
final readonly class DispatchEntry
{
    /**
     * @param class-string $apiClassName
     * @param list<ArgumentBinding> $arguments
     * @param TypeReference|null $successType the *declared* type of a successful result, or null for `void`.
     *                                        Declared rather than the returned value's own class, because a
     *                                        polymorphic return type has to be serialized as the union — that is
     *                                        what carries the discriminator tag.
     */
    public function __construct(
        public string $apiClassName,
        public string $methodName,
        public array $arguments,
        public string|null $operationId,
        public TypeReference|null $successType = null,
    ) {}
}
