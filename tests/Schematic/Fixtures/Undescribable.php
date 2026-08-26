<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

/**
 * An interface with no `#[Discriminator]`: nothing enumerates its implementations, so the engine cannot describe
 * it — the failure core must see is its own exception, not the engine's.
 */
interface Undescribable {}
