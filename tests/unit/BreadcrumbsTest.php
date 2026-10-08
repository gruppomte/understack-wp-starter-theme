<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Breadcrumbs;

test('breadcrumbs: the last item is the current page and loses its link', function (): void {
    $trail = Breadcrumbs::finish([
        ['name' => 'Home', 'url' => 'https://example.com/'],
        ['name' => 'About us', 'url' => 'https://example.com/about/'],
        ['name' => 'Team', 'url' => 'https://example.com/about/team/'],
    ]);

    assertSame([
        ['name' => 'Home', 'url' => 'https://example.com/'],
        ['name' => 'About us', 'url' => 'https://example.com/about/'],
        ['name' => 'Team', 'url' => null],
    ], $trail);
});

test('breadcrumbs: nameless items are dropped and a lone home item renders nothing', function (): void {
    assertSame([], Breadcrumbs::finish([['name' => 'Home', 'url' => 'https://example.com/']]));
    assertSame([], Breadcrumbs::finish([
        ['name' => 'Home', 'url' => 'https://example.com/'],
        ['name' => '  ', 'url' => null],
    ]));
    assertSame([], Breadcrumbs::finish([]));
});

test('breadcrumbs: names are plain text', function (): void {
    $trail = Breadcrumbs::finish([
        ['name' => 'Home', 'url' => 'https://example.com/'],
        ['name' => '<em>News</em> &amp; views', 'url' => 'https://example.com/news/'],
    ]);

    assertSame('News & views', $trail[1]['name']);
});

test('breadcrumbs: paginated listings link the listing and add the page number', function (): void {
    $trail = Breadcrumbs::finish([
        ['name' => 'Home', 'url' => 'https://example.com/'],
        ['name' => 'News', 'url' => 'https://example.com/category/news/'],
    ], 2, 'https://example.com/category/news/page/2/', 'Page 2');

    assertSame([
        ['name' => 'Home', 'url' => 'https://example.com/'],
        ['name' => 'News', 'url' => 'https://example.com/category/news/'],
        ['name' => 'Page 2', 'url' => null],
    ], $trail);
});
