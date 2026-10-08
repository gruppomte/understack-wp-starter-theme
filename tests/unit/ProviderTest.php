<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Provider;

test('provider: nothing defined means no provider', function (): void {
    assertSame(Provider::None, Provider::detect(static fn (string $constant): bool => false));
});

test('provider: each plugin is recognised by its version constant', function (): void {
    foreach (Provider::cases() as $provider) {
        if ($provider === Provider::None) {
            continue;
        }

        $detected = Provider::detect(static fn (string $constant): bool => $constant === $provider->constant());
        assertSame($provider, $detected, $provider->label());
    }
});

test('provider: inactive plugins define nothing and are not detected', function (): void {
    $defined = ['WP_DEBUG' => true, 'RANK_MATH_FILE' => true];

    assertSame(Provider::None, Provider::detect(static fn (string $constant): bool => isset($defined[$constant])));
});

test('provider: only measured providers are marked verified', function (): void {
    assertTrue(Provider::RankMath->isVerified());
    assertTrue(Provider::Yoast->isVerified());
    assertTrue(Provider::None->isVerified());
    assertFalse(Provider::SeoPress->isVerified());
});
