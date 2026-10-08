<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Diagnostics\Checks;

// Facts of a healthy production site without plugins.
$healthyFacts = static function (): array {
    return [
        'environment' => ['type' => 'production', 'declared' => true, 'noindex' => false, 'warnings' => []],
        'blog_public' => 1,
        'provider' => [
            'id' => 'none',
            'label' => 'none',
            'version' => '',
            'forced' => false,
            'detected_id' => 'none',
            'detected_label' => 'none',
            'verified' => true,
            'dormant' => false,
        ],
        'ownership' => ['title' => 'core', 'description' => 'theme', 'structured_data' => 'theme'],
        'sitemap' => ['owner' => 'core', 'url' => 'https://example.com/wp-sitemap.xml'],
        'identity' => ['configured' => true, 'schema_type' => 'Organization', 'name' => 'Alfa', 'issues' => []],
        'page_type_issues' => [],
        'breadcrumbs_enabled' => false,
        'crawlers' => ['policy' => 'open', 'invalid' => false, 'groups' => 0, 'agents' => []],
        'robots_txt_file' => false,
        'config_issues' => [],
        'wpml' => ['active' => false, 'version' => '', 'language' => ''],
        'woocommerce' => ['active' => false, 'version' => ''],
    ];
};

// Status of one check by id.
$statusOf = static function (array $checks, string $id): string {
    foreach ($checks as $check) {
        if ($check['id'] === $id) {
            return $check['status'];
        }
    }

    return 'missing';
};

test('checks: healthy production site passes every measurable check', function () use ($healthyFacts, $statusOf): void {
    $checks = Checks::evaluate($healthyFacts());
    $summary = Checks::summary($checks);

    assertSame(0, $summary['WARNING']);
    assertSame(0, $summary['FAIL']);
    assertSame(6, $summary['NOT VERIFIED']);
    assertSame('PASS', $statusOf($checks, 'crawler_policy'));
    assertSame('NOT VERIFIED', $statusOf($checks, 'search_engines'));
    assertSame('NOT VERIFIED', $statusOf($checks, 'cdn_waf'));
    assertSame('NOT VERIFIED', $statusOf($checks, 'ai_fetchers'));
});

test('checks: discouraged search engines fail on production only', function () use ($healthyFacts, $statusOf): void {
    $production = Checks::evaluate(['blog_public' => 0] + $healthyFacts());
    assertSame('FAIL', $statusOf($production, 'visibility'));

    $facts = $healthyFacts();
    $facts['environment'] = ['type' => 'staging', 'declared' => true, 'noindex' => true, 'warnings' => []];
    $staging = Checks::evaluate(['blog_public' => 0] + $facts);
    assertSame('PASS', $statusOf($staging, 'visibility'));
    assertSame('PASS', $statusOf($staging, 'indexability'));
});

test('checks: undeclared or contradictory environments warn', function () use ($healthyFacts, $statusOf): void {
    $facts = $healthyFacts();
    $facts['environment'] = [
        'type' => 'production',
        'declared' => false,
        'noindex' => false,
        'warnings' => ['Host mismatch.'],
    ];
    $checks = Checks::evaluate($facts);

    assertSame('WARNING', $statusOf($checks, 'environment'));
    assertSame('WARNING', $statusOf($checks, 'indexability'));
});

test('checks: the crawler policy reports the owner decision', function () use ($healthyFacts, $statusOf): void {
    $with = static fn (array $crawlers): array => Checks::evaluate(['crawlers' => $crawlers] + $healthyFacts());

    $unset = $with(['policy' => 'unset', 'invalid' => false, 'groups' => 0, 'agents' => []]);
    assertSame('WARNING', $statusOf($unset, 'crawler_policy'));

    $custom = $with(['policy' => 'custom', 'invalid' => false, 'groups' => 2, 'agents' => ['A', 'B']]);
    assertSame('PASS', $statusOf($custom, 'crawler_policy'));

    $broken = $with(['policy' => 'custom', 'invalid' => true, 'groups' => 0, 'agents' => []]);
    assertSame('FAIL', $statusOf($broken, 'crawler_policy'));
});

test('checks: provider states', function () use ($healthyFacts, $statusOf): void {
    $facts = $healthyFacts();
    $facts['provider'] = [
        'id' => 'yoast',
        'label' => 'Yoast SEO',
        'version' => '28.6',
        'forced' => false,
        'detected_id' => 'yoast',
        'detected_label' => 'Yoast SEO',
        'verified' => true,
        'dormant' => false,
    ];
    $facts['ownership']['structured_data'] = 'provider';
    $facts['sitemap'] = ['owner' => 'provider', 'url' => ''];
    $yoast = Checks::evaluate($facts);
    assertSame('PASS', $statusOf($yoast, 'provider'));
    assertSame('PASS', $statusOf($yoast, 'structured_data'));
    assertSame('PASS', $statusOf($yoast, 'sitemap'));

    $facts['provider']['id'] = 'rank-math';
    $facts['provider']['dormant'] = true;
    assertSame('WARNING', $statusOf(Checks::evaluate($facts), 'provider'));

    $facts['provider'] = [
        'id' => 'seopress',
        'label' => 'SEOPress',
        'version' => '10.3',
        'forced' => false,
        'detected_id' => 'seopress',
        'detected_label' => 'SEOPress',
        'verified' => false,
        'dormant' => false,
    ];
    assertSame('WARNING', $statusOf(Checks::evaluate($facts), 'provider'));

    $facts['provider'] = [
        'id' => 'yoast',
        'label' => 'Yoast SEO',
        'version' => '',
        'forced' => true,
        'detected_id' => 'none',
        'detected_label' => 'none',
        'verified' => true,
        'dormant' => false,
    ];
    assertSame('WARNING', $statusOf(Checks::evaluate($facts), 'provider'));
});

test('checks: identity, page types, configuration and integrations', function () use ($healthyFacts, $statusOf): void {
    $facts = $healthyFacts();
    $facts['identity'] = ['configured' => false, 'schema_type' => '', 'name' => '', 'issues' => []];
    $facts['page_type_issues'] = ['structured_data.about_page points to 9, which is not a published page.'];
    $facts['config_issues'] = ['Unknown key "typo" ignored.'];
    $facts['robots_txt_file'] = true;
    $facts['wpml'] = ['active' => true, 'version' => '4.7', 'language' => 'it'];
    $facts['woocommerce'] = ['active' => true, 'version' => '10.0'];
    $checks = Checks::evaluate($facts);

    assertSame('WARNING', $statusOf($checks, 'structured_data'));
    assertSame('WARNING', $statusOf($checks, 'identity'));
    assertSame('FAIL', $statusOf($checks, 'page_types'));
    assertSame('WARNING', $statusOf($checks, 'configuration'));
    assertSame('WARNING', $statusOf($checks, 'robots_txt'));
    assertSame('NOT VERIFIED', $statusOf($checks, 'wpml'));
    assertSame('PASS', $statusOf($checks, 'woocommerce'));
});
