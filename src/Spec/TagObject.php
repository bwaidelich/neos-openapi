<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * A named group of operations, which is what makes a document spanning several Api Classes readable in a UI.
 *
 * The predecessor had no tag support at all and typed the root `tags` member as an array of strings; the
 * specification defines it as an array of these objects.
 *
 * @see https://spec.openapis.org/oas/v3.1.1#tag-object
 */
final readonly class TagObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public string $name,
        public string|null $description = null,
        public ExternalDocumentationObject|null $externalDocs = null,
    ) {}
}
