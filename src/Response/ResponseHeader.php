<?php

declare(strict_types=1);

namespace Neos\OpenApi\Response;

use Neos\OpenApi\Binding\TypeReference;

/**
 * One header an {@see ApiResponseWithHeaders} declares — its name, and the type of its value as a
 * {@see TypeReference}.
 *
 * A TypeReference rather than a bare "it is a string" for the same reason a body has one: the schema the document
 * publishes for the header and the value the runtime writes into it come from the same {@see TypeBinding}, so a
 * `Location` typed as a `Url` is documented as a `Url` and rendered by the very code that described it.
 *
 * `Content-Type` is rejected: the specification says a response header of that name SHALL be ignored, and the
 * response's own {@see ApiResponse::contentType()} is what sets it.
 */
final readonly class ResponseHeader
{
    /**
     * RFC 9110's `token`, which is what a field name may consist of.
     */
    private const NAME_PATTERN = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/';

    private function __construct(
        public string $name,
        public TypeReference $type,
        public string|null $description,
        public bool $required,
        public bool $deprecated,
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid response header name "%s"', $name), 1783500210);
        }
        if (strcasecmp($name, 'Content-Type') === 0) {
            throw new \InvalidArgumentException(
                'A response may not declare a "Content-Type" header: the specification says one is ignored, and '
                . 'ApiResponse::contentType() is what sets it',
                1783500211,
            );
        }
    }

    /**
     * @param string $name the field name, in the casing the document should show
     * @param bool $required whether every instance of this response carries the header. Defaults to `true`,
     *        because a header a response bothers to declare is normally one it always sends — and the runtime
     *        holds it to that: a required header absent from {@see ApiResponseWithHeaders::headers()} is a bug,
     *        not an empty header.
     */
    public static function create(
        string $name,
        TypeReference $type,
        string|null $description = null,
        bool $required = true,
        bool $deprecated = false,
    ): self {
        return new self($name, $type, $description, $required, $deprecated);
    }
}
