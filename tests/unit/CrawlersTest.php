<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Config;
use Understack\Seo\Crawlers\Groups;

test('crawlers: valid groups are normalised and rendered as robots.txt blocks', function (): void {
    $groups = Groups::normalise([
        ['agents' => ['ExampleBot', ' OtherBot ', 'ExampleBot'], 'disallow' => ['/', '/'], 'crawl_delay' => 10],
        ['agent' => '*', 'allow' => ['/public/'], 'disallow' => '/private/'],
    ]);

    assertSame([
        ['agents' => ['ExampleBot', 'OtherBot'], 'allow' => [], 'disallow' => ['/'], 'crawl_delay' => 10],
        ['agents' => ['*'], 'allow' => ['/public/'], 'disallow' => ['/private/'], 'crawl_delay' => null],
    ], $groups);
    assertSame(
        "User-agent: ExampleBot\nUser-agent: OtherBot\nDisallow: /\nCrawl-delay: 10\n\n"
            . "User-agent: *\nAllow: /public/\nDisallow: /private/",
        Groups::render($groups)
    );
    assertSame(['ExampleBot', 'OtherBot', '*'], Groups::agents($groups));
});

test('crawlers: invalid groups are rejected as a whole', function (): void {
    $invalid = [
        'not a list',
        [['agents' => [], 'disallow' => ['/']]],
        [['agents' => ['Bot'], 'disallow' => []]],
        [['agents' => ['Bot'], 'disallow' => ['private']]],
        [['agents' => ['Bot'], 'disallow' => ["/\nSitemap: https://evil.example"]]],
        [['agents' => ['Bad Bot'], 'disallow' => ['/']]],
        [['agents' => ['Bot'], 'disallow' => ['/'], 'crawl_delay' => 0]],
        [['agents' => ['Bot'], 'disallow' => ['/'], 'crawl_delay' => '10']],
        [['agents' => ['Bot'], 'disallow' => ['/'], 'extra' => true, 'allow' => 'relative']],
    ];

    foreach ($invalid as $index => $value) {
        assertSame(null, Groups::normalise($value), 'case ' . $index);
    }
});

test('crawlers: the configuration keeps the policy unset and empty by default', function (): void {
    $config = Config::fromArray([]);

    assertSame('unset', $config->string('crawlers.policy'));
    assertSame([], $config->get('crawlers.groups'));
});

test('crawlers: a custom policy is validated through the configuration', function (): void {
    $config = Config::fromArray([
        'crawlers' => ['policy' => 'custom', 'groups' => [['agents' => ['ExampleBot'], 'disallow' => ['/']]]],
    ]);

    assertSame('custom', $config->string('crawlers.policy'));
    assertSame('ExampleBot', $config->get('crawlers.groups')[0]['agents'][0] ?? null);
    assertSame([], $config->issues());

    $broken = Config::fromArray(['crawlers' => ['policy' => 'custom', 'groups' => [['agents' => ['Bot']]]]]);

    assertSame([], $broken->get('crawlers.groups'));
    assertStringContains('"crawlers.groups"', $broken->issues()[0]);
    assertSame('open', Config::fromArray(['crawlers' => ['policy' => 'open']])->string('crawlers.policy'));
});
