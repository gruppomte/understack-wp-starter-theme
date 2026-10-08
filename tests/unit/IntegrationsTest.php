<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Integrations\WooCommerce;
use Understack\Seo\Integrations\Wpml;

test('wpml: configured page IDs are mapped through the translator and fall back to the original', function (): void {
    $map = ['12' => 34];
    $translator = static fn (int $id): mixed => $map[(string) $id] ?? $id;

    assertSame(34, Wpml::translate(12, $translator));
    assertSame(7, Wpml::translate(7, $translator));
    assertSame(0, Wpml::translate(0, $translator));
    assertSame(5, Wpml::translate(5, static fn (int $id): mixed => null));
    assertSame(5, Wpml::translate(5, static fn (int $id): mixed => 'not a number'));
    assertSame(5, Wpml::translate(5, static fn (int $id): mixed => -1));
});

test('woocommerce: shop pages keep description and social but no graph or breadcrumbs', function (): void {
    assertSame(
        ['description' => true, 'social' => true, 'graph' => false, 'breadcrumbs' => false],
        WooCommerce::allowances(true, false)
    );
});

test('woocommerce: transactional pages get nothing from the theme', function (): void {
    assertSame(
        ['description' => false, 'social' => false, 'graph' => false, 'breadcrumbs' => false],
        WooCommerce::allowances(false, true)
    );
    assertSame(
        ['description' => false, 'social' => false, 'graph' => false, 'breadcrumbs' => false],
        WooCommerce::allowances(true, true)
    );
});

test('woocommerce: ordinary pages are unaffected', function (): void {
    assertSame(
        ['description' => true, 'social' => true, 'graph' => true, 'breadcrumbs' => true],
        WooCommerce::allowances(false, false)
    );
});
