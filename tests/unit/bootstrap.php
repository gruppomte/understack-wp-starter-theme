<?php

/**
 * Test runner and assertion helpers for tests/unit (loaded by run.php).
 */

declare(strict_types=1);

namespace Understack\Tests\Unit;

use RuntimeException;
use Throwable;

// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode -- WordPress is not loaded here.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- command-line runner, no HTML context.

final class UnitTestRunner
{
    /** @var array<int, array{0: string, 1: callable}> */
    private static array $tests = [];

    public static function add(string $name, callable $test): void
    {
        self::$tests[] = [$name, $test];
    }

    public static function run(?string $filter): int
    {
        $failures = 0;

        foreach (self::$tests as [$name, $test]) {
            if ($filter !== null && ! str_contains($name, $filter)) {
                continue;
            }

            try {
                $test();
                fwrite(STDOUT, "[ ok ] {$name}\n");
            } catch (Throwable $exception) {
                $failures++;
                fwrite(STDOUT, "[FAIL] {$name}\n       {$exception->getMessage()}\n");
            }
        }

        fwrite(STDOUT, $failures === 0 ? "Unit tests passed.\n" : sprintf("%d test(s) failed.\n", $failures));

        return $failures;
    }
}

function test(string $name, callable $test): void
{
    UnitTestRunner::add($name, $test);
}

function describe(mixed $value): string
{
    return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            trim($message . ' expected ' . describe($expected) . ', got ' . describe($actual))
        );
    }
}

function assertTrue(mixed $actual, string $message = ''): void
{
    assertSame(true, $actual, $message);
}

function assertFalse(mixed $actual, string $message = ''): void
{
    assertSame(false, $actual, $message);
}

function assertStringContains(string $needle, string $haystack, string $message = ''): void
{
    if (! str_contains($haystack, $needle)) {
        throw new RuntimeException(
            trim($message . ' expected ' . describe($haystack) . ' to contain ' . describe($needle))
        );
    }
}
