<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Indexability\Environment;

test('environment: declared staging, development and local copies are not indexable', function (): void {
    foreach (['staging', 'development', 'local', 'Staging '] as $type) {
        $verdict = Environment::evaluate($type, true, 'staging.example.com', []);

        assertTrue($verdict->noindex, $type);
        assertSame([], $verdict->warnings, $type);
    }
});

test('environment: declared production is indexable without warnings', function (): void {
    $verdict = Environment::evaluate('production', true, 'www.example.com', []);

    assertFalse($verdict->noindex);
    assertSame([], $verdict->warnings);
});

test('environment: an undeclared type is treated as production and reported', function (): void {
    $verdict = Environment::evaluate('production', false, 'www.example.com', []);

    assertFalse($verdict->noindex);
    assertSame(1, count($verdict->warnings));
    assertStringContains('WP_ENVIRONMENT_TYPE is not declared', $verdict->warnings[0]);
});

test('environment: a declared production host wins over a non-production type', function (): void {
    $verdict = Environment::evaluate('staging', true, 'WWW.Example.com', ['www.example.com']);

    assertFalse($verdict->noindex);
    assertStringContains('contradicts', $verdict->warnings[0]);
});

test('environment: a non-production type on a host outside the production list is noindex', function (): void {
    $verdict = Environment::evaluate('staging', true, 'staging.example.com', ['www.example.com']);

    assertTrue($verdict->noindex);
    assertSame([], $verdict->warnings);
});

test('environment: production on a host outside the list is reported but stays indexable', function (): void {
    $verdict = Environment::evaluate('production', true, 'preview.example.com', ['www.example.com']);

    assertFalse($verdict->noindex);
    assertStringContains('not in indexability.production_hosts', $verdict->warnings[0]);
});

test('environment: the policy can be switched off', function (): void {
    $verdict = Environment::evaluate('staging', true, 'staging.example.com', [], 'off');

    assertFalse($verdict->noindex);
    assertStringContains('switched off', $verdict->reasons[0]);
});

test('environment: an empty type falls back to production', function (): void {
    assertSame('production', Environment::evaluate('', false, 'example.com', [])->type);
});
