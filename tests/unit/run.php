<?php

/**
 * Unit tests for the pure parts of the theme (no WordPress needed):
 *   php tests/unit/run.php [name filter]
 *
 * Each tests/unit/*Test.php file registers tests with test('name', fn () => ...)
 * and the assertion helpers from bootstrap.php. Exits with 1 when a test fails.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}

exit(Understack\Tests\Unit\UnitTestRunner::run($argv[1] ?? null) === 0 ? 0 : 1);
