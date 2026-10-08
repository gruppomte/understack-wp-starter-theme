<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Config;
use Understack\Seo\Owner;
use Understack\Seo\Ownership;
use Understack\Seo\Provider;
use Understack\Seo\Signal;

test('ownership: without a provider the theme fills the gaps and core keeps the rest', function (): void {
    $ownership = Ownership::resolve(Provider::None, Config::fromArray([]));

    assertSame(Owner::Core, $ownership->of(Signal::Title));
    assertSame(Owner::Theme, $ownership->of(Signal::Description));
    assertSame(Owner::Theme, $ownership->of(Signal::Canonical));
    assertSame(Owner::Core, $ownership->of(Signal::Robots));
    assertSame(Owner::Theme, $ownership->of(Signal::Social));
    assertSame(Owner::Core, $ownership->of(Signal::Sitemap));
    assertSame(Owner::Core, $ownership->of(Signal::RobotsTxt));
});

test('ownership: a provider owns every signal', function (): void {
    foreach ([Provider::RankMath, Provider::Yoast, Provider::SeoPress] as $provider) {
        $ownership = Ownership::resolve($provider, Config::fromArray([]));

        foreach (Signal::cases() as $signal) {
            assertSame(Owner::Provider, $ownership->of($signal), $provider->label() . ' ' . $signal->value);
        }
    }
});

test('ownership: with_provider keeps selected theme signals while a provider is active', function (): void {
    $ownership = Ownership::resolve(Provider::Yoast, Config::fromArray(['with_provider' => ['description']]));

    assertSame(Owner::Theme, $ownership->of(Signal::Description));
    assertSame(Owner::Provider, $ownership->of(Signal::Canonical));
    assertSame(Owner::Provider, $ownership->of(Signal::Social));
});

test('ownership: disabled fallbacks leave a signal to nobody or to core', function (): void {
    $ownership = Ownership::resolve(Provider::None, Config::fromArray([
        'fallbacks' => ['description' => false, 'canonical' => false, 'social' => false],
    ]));

    assertSame(Owner::None, $ownership->of(Signal::Description));
    assertSame(Owner::Core, $ownership->of(Signal::Canonical));
    assertSame(Owner::None, $ownership->of(Signal::Social));
    assertSame([
        'title' => 'core',
        'description' => 'none',
        'canonical' => 'core',
        'robots' => 'core',
        'social' => 'none',
        'sitemap' => 'core',
        'robots_txt' => 'core',
    ], $ownership->toArray());
});
