<?php

declare(strict_types=1);

namespace Neos\OpenApi\Support;

/**
 * Serializes an object's members, omitting the ones that are `null`.
 *
 * Almost every object in the OpenAPI specification has a handful of required members and a long tail of optional
 * ones, and an absent member must not appear in the document at all — `"description": null` is not the same as no
 * description. The predecessor repeated this one-liner in some thirty classes; here it is written once.
 */
trait SerializesNonNullMembers
{
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $members = [];
        // called from within the class, so private members are in scope
        foreach (get_object_vars($this) as $name => $member) {
            if ($member !== null) {
                $members[(string) $name] = $member;
            }
        }
        return $members;
    }
}
