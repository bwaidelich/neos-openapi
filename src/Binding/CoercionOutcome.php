<?php

declare(strict_types=1);

namespace Neos\OpenApi\Binding;

use Neos\JsonSchema\Validation\Issues;

/**
 * The result of coercing request data against a {@see TypeBinding}: *either* the value, *or* why it was rejected.
 *
 * A core type rather than the schema engine's own result object, so the port does not leak whichever engine is
 * behind it. The `Issues` come from `neos/jsonschema`, which core depends on anyway.
 */
final readonly class CoercionOutcome
{
    private function __construct(
        public bool $success,
        private mixed $value,
        public Issues|null $issues,
    ) {}

    public static function ok(mixed $value): self
    {
        return new self(true, $value, null);
    }

    public static function failed(Issues $issues): self
    {
        return new self(false, null, $issues);
    }

    /**
     * @throws \LogicException if the coercion failed — inspect {@see self::$success} first
     */
    public function value(): mixed
    {
        if (!$this->success) {
            throw new \LogicException('Cannot read the value of a failed coercion', 1783500200);
        }
        return $this->value;
    }
}
