<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Config;

test('config: defaults apply without overrides', function (): void {
    $config = Config::fromArray([]);

    assertTrue($config->bool('enabled'));
    assertSame('auto', $config->string('provider'));
    assertTrue($config->bool('fallbacks.description'));
    assertSame('noindex', $config->string('indexability.non_production'));
    assertSame([], $config->list('indexability.production_hosts'));
    assertFalse($config->bool('sitemap.users'));
    assertSame(['post'], $config->list('content.article_post_types'));
    assertSame([], $config->issues());
});

test('config: valid overrides are normalised', function (): void {
    $config = Config::fromArray([
        'provider' => 'none',
        'fallbacks' => ['social' => false],
        'indexability' => ['production_hosts' => ['Example.com', 'https://www.example.com/path', ' example.com ']],
        'social' => ['twitter_site' => 'MyBrand', 'default_image' => 12],
        'content' => ['article_post_types' => ['Post', 'news']],
    ]);

    assertSame('none', $config->string('provider'));
    assertFalse($config->bool('fallbacks.social'));
    assertTrue($config->bool('fallbacks.description'));
    assertSame(['example.com', 'www.example.com'], $config->list('indexability.production_hosts'));
    assertSame('@MyBrand', $config->string('social.twitter_site'));
    assertSame(12, $config->int('social.default_image'));
    assertSame(['post', 'news'], $config->list('content.article_post_types'));
    assertSame([], $config->issues());
});

test('config: invalid values keep the default and are reported', function (): void {
    $config = Config::fromArray([
        'enabled' => 'yes',
        'provider' => 'unknown-plugin',
        'with_provider' => ['description', 'robots'],
        'social' => ['twitter_site' => 'not a handle', 'default_image' => -1],
        'indexability' => ['non_production' => 'maybe'],
    ]);

    assertTrue($config->bool('enabled'));
    assertSame('auto', $config->string('provider'));
    assertSame([], $config->list('with_provider'));
    assertSame('', $config->string('social.twitter_site'));
    assertSame(0, $config->int('social.default_image'));
    assertSame('noindex', $config->string('indexability.non_production'));
    assertSame(6, count($config->issues()), describe($config->issues()));
    assertStringContains('"enabled"', $config->issues()[0]);
});

test('config: unknown keys are ignored and reported', function (): void {
    $config = Config::fromArray(['typo' => true, 'fallbacks' => ['titles' => false]]);

    assertSame(2, count($config->issues()));
    assertStringContains('Unknown key "typo"', $config->issues()[0]);
    assertStringContains('Unknown key "fallbacks.titles"', $config->issues()[1]);
});

test('config: a handle may be given with or without the at sign', function (): void {
    $config = Config::fromArray(['social' => ['twitter_site' => '@brand_1']]);

    assertSame('@brand_1', $config->string('social.twitter_site'));
    assertSame('', Config::fromArray(['social' => ['twitter_site' => '']])->string('social.twitter_site'));
});
