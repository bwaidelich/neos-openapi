<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Documentation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

/**
 * Executes every PHP example in the README, so the documentation cannot drift away from the code.
 *
 * The conventions an example follows:
 *
 * - Every ` ```php ` block is executed. Mark one ` ```php (no test) ` to exclude it.
 * - A block whose first line is `// ...` *continues* the previous one: it is evaluated in the same namespace and
 *   the same variable scope, and the `use` statements of the preceding blocks are re-applied — so one example can
 *   be told in several steps without repeating its setup.
 * - `assert(...)` is rewritten into a PHPUnit assertion before evaluation. Plain `assert()` would be compiled
 *   away under `zend.assertions=-1` (what php.ini-production ships, and with it most CI setups), which would make
 *   these tests silently vacuous. The rewrite is a straight swap of the function being called, so the examples
 *   stay honest PHP that a reader can copy.
 * - Every example must assert at least once: a block of code nobody checks is exactly the documentation that
 *   rots.
 */
#[CoversNothing]
final class ReadmeCodeBlockTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{line: int, code: string}>}>
     */
    public static function examples(): iterable
    {
        $path = realpath(__DIR__ . '/../../README.md');
        self::assertIsString($path, 'README.md not found');
        $contents = file_get_contents($path);
        self::assertIsString($contents, 'README.md could not be read');

        /** @var list<array{heading: string, line: int, code: string, continuation: bool}> $blocks */
        $blocks = [];
        $heading = '';
        $open = null;
        foreach (explode("\n", $contents) as $index => $line) {
            if ($open === null) {
                if (str_starts_with($line, '#')) {
                    $heading = trim($line, "# \t\r");
                } elseif (str_starts_with($line, '```php') && !str_contains($line, '(no test)')) {
                    $open = ['heading' => $heading, 'line' => $index + 1, 'code' => []];
                }
                continue;
            }
            if (rtrim($line) === '```') {
                $code = implode("\n", $open['code']);
                $blocks[] = [
                    'heading' => $open['heading'],
                    'line' => $open['line'],
                    'code' => $code,
                    'continuation' => str_starts_with(ltrim($code), '// ...'),
                ];
                $open = null;
                continue;
            }
            $open['code'][] = $line;
        }
        self::assertNotSame([], $blocks, 'The README contains no executable PHP examples');

        // group each block with the `// ...` continuations that follow it
        /** @var list<array{heading: string, line: int, blocks: list<array{line: int, code: string}>}> $chains */
        $chains = [];
        /** @var array{heading: string, line: int, blocks: list<array{line: int, code: string}>}|null $chain */
        $chain = null;
        foreach ($blocks as $block) {
            if (!$block['continuation'] || $chain === null) {
                if ($chain !== null) {
                    $chains[] = $chain;
                }
                $chain = ['heading' => $block['heading'], 'line' => $block['line'], 'blocks' => []];
            }
            $chain['blocks'][] = ['line' => $block['line'], 'code' => $block['code']];
        }
        if ($chain !== null) {
            $chains[] = $chain;
        }

        foreach ($chains as $chain) {
            $name = sprintf('README.md line %d: %s', $chain['line'], $chain['heading']);
            yield $name => ['Line_' . $chain['line'], $chain['blocks']];
        }
    }

    /**
     * @param string $namespaceSuffix a namespace of its own per example, so identically named classes don't clash
     * @param list<array{line: int, code: string}> $blocks the example's blocks, in order
     */
    #[DataProvider('examples')]
    public function testExample(string $namespaceSuffix, array $blocks): void
    {
        $namespace = __NAMESPACE__ . '\\Readme\\' . $namespaceSuffix;
        $imports = [];
        $assertions = 0;
        foreach ($blocks as $block) {
            $statements = [];
            foreach (explode("\n", $block['code']) as $line) {
                $trimmed = trim($line);
                if ($trimmed === '<?php' || str_starts_with($trimmed, 'declare(strict_types')) {
                    continue;
                }
                if (preg_match('/^use\s+[^;]+;$/', $trimmed) === 1) {
                    $imports[$trimmed] = true;
                    continue;
                }
                $statements[] = $line;
            }
            $body = implode("\n", $statements);
            $assertions += preg_match_all('/(?<![\w$>:\\\\])assert\s*\(/', $body);
            $body = preg_replace('/(?<![\w$>:\\\\])assert\s*\(/', '\\PHPUnit\\Framework\\Assert::assertTrue(', $body);
            self::assertIsString($body);

            // the eval'd blocks of one example share this scope, so a later block can use earlier variables
            try {
                eval(sprintf("namespace %s {\n%s\n%s\n}", $namespace, implode("\n", array_keys($imports)), $body));
            } catch (ExpectationFailedException $exception) {
                // point at the block that drifted, which is rarely the one the test is named after
                self::fail(sprintf('%s (in the README block starting on line %d)', $exception->getMessage(), $block['line']));
            }
        }
        self::assertGreaterThan(0, $assertions, 'Every README example must verify itself with at least one assert(...)');
    }
}
