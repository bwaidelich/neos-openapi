<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\OpenApi\Binding\CoercionOutcome;

/**
 * What the fixture types of these tests can do, in place of a schema engine.
 *
 * The request handler is *core*, so its tests may not reach for `neos/schematic` (ADR 0002) — but they need
 * types that really are built from request data and really are read back out, or they would prove nothing about
 * the plumbing. So the fixtures describe themselves, and `tests/Schematic/RequestHandlingTest.php` covers the
 * same ground with the real engine behind the port.
 */
interface Coercible
{
    public static function coerce(mixed $input): CoercionOutcome;

    public function serialize(): mixed;
}
