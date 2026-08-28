<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

/**
 * An interface, so it owns no schema and nothing can derive one for it — the failure core must see is its own
 * exception, not the engine's.
 */
interface Undescribable {}
