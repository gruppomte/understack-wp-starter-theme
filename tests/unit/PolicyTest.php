<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Config;
use Understack\Seo\Indexability\Policy;
use Understack\Seo\PageContext;
use Understack\Seo\PageKind;

$sortedRobots = static function (array $robots): array {
    ksort($robots);

    return $robots;
};

test('policy: forcing noindex replaces index, follow and the snippet limits', function () use ($sortedRobots): void {
    $robots = Policy::forceNoindex([
        'index' => true,
        'follow' => true,
        'max-image-preview' => 'large',
        'max-snippet' => '-1',
    ]);

    assertSame(['nofollow' => true, 'noindex' => true], $sortedRobots($robots));
});

test('policy: forcing noindex is idempotent on a non-public site', function () use ($sortedRobots): void {
    $robots = Policy::forceNoindex(['noindex' => true, 'nofollow' => true]);

    assertSame(['nofollow' => true, 'noindex' => true], $sortedRobots($robots));
});

test('policy: 404 and attachment pages get noindex by default', function () use ($sortedRobots): void {
    $config = Config::fromArray([]);
    $base = ['max-image-preview' => 'large'];

    $notFound = Policy::withPageDirectives($base, new PageContext(PageKind::NotFound), $config);
    assertSame(['follow' => true, 'max-image-preview' => 'large', 'noindex' => true], $sortedRobots($notFound));

    $attachment = Policy::withPageDirectives($base, new PageContext(PageKind::Singular, 'attachment', 5), $config);
    assertTrue($attachment['noindex'] ?? false);

    $post = Policy::withPageDirectives($base, new PageContext(PageKind::Singular, 'post', 5), $config);
    assertSame($base, $post);
});

test('policy: configured post types and taxonomies are noindex, others untouched', function (): void {
    $config = Config::fromArray(['indexability' => ['post_types' => ['internal'], 'taxonomies' => ['post_format']]]);

    $noindex = static fn (PageContext $context): bool
        => Policy::withPageDirectives([], $context, $config)['noindex'] ?? false;

    assertTrue($noindex(new PageContext(PageKind::Singular, 'internal', 1)));
    assertTrue($noindex(new PageContext(PageKind::PostTypeArchive, 'internal')));
    assertTrue($noindex(new PageContext(PageKind::Term, 'post_format', 3)));
    assertFalse($noindex(new PageContext(PageKind::Term, 'category', 3)));
});

test('policy: page directives can be switched to index', function (): void {
    $config = Config::fromArray(['indexability' => ['not_found' => 'index', 'attachments' => 'index']]);

    assertSame([], Policy::withPageDirectives([], new PageContext(PageKind::NotFound), $config));
    assertSame([], Policy::withPageDirectives([], new PageContext(PageKind::Singular, 'attachment', 5), $config));
});
