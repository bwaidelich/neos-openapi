<?php

declare(strict_types=1);

namespace Neos\OpenApi\Attributes;

use Attribute;
use Neos\OpenApi\Spec\SecurityRequirementObject;
use Neos\OpenApi\Support\HttpMethod;
use Neos\OpenApi\Support\RelativePath;

/**
 * Marks a public method of an Api Class as one HTTP operation.
 *
 * Security is given as plain values rather than a {@see SecurityRequirementObject}, because attribute arguments
 * must be constant expressions and that object is built through named constructors. A string names one scheme;
 * an array maps scheme names to the scopes they must grant, all of which apply together.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Operation
{
    public readonly RelativePath $path;
    public readonly HttpMethod $method;
    public readonly SecurityRequirementObject|null $security;

    /**
     * @param array<string, list<string>>|string|null $security
     */
    public function __construct(
        RelativePath|string $path,
        HttpMethod|string $method,
        public readonly string|null $summary = null,
        public readonly string|null $description = null,
        public readonly string|null $operationId = null,
        array|string|null $security = null,
        public readonly bool $allowAnonymous = false,
        public readonly bool|null $deprecated = null,
    ) {
        $this->path = is_string($path) ? RelativePath::fromString($path) : $path;
        $this->method = is_string($method) ? HttpMethod::from(strtoupper($method)) : $method;
        $this->security = self::requirement($security, $allowAnonymous);
    }

    /**
     * @param array<string, list<string>>|string|null $security
     */
    private static function requirement(array|string|null $security, bool $allowAnonymous): SecurityRequirementObject|null
    {
        if ($security === null) {
            return null;
        }
        $requirement = is_string($security)
            ? SecurityRequirementObject::scheme($security)
            : SecurityRequirementObject::all($security);
        return $allowAnonymous ? $requirement->orAnonymously() : $requirement;
    }
}
