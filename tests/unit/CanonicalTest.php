<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Meta\Canonical;
use Understack\Seo\PageContext;
use Understack\Seo\PageKind;

test('canonical: the first page keeps the base URL', function (): void {
    assertSame('https://example.com/news/', Canonical::paged('https://example.com/news/', 1, true));
});

test('canonical: pretty permalinks add the pagination segment', function (): void {
    assertSame('https://example.com/news/page/3/', Canonical::paged('https://example.com/news/', 3, true));
    assertSame('https://example.com/news/page/3', Canonical::paged('https://example.com/news', 3, true, 'page', false));
    assertSame('https://example.com/pagina/2/', Canonical::paged('https://example.com/', 2, true, 'pagina'));
});

test('canonical: plain permalinks and query strings use the paged parameter', function (): void {
    assertSame('https://example.com/?paged=2', Canonical::paged('https://example.com/', 2, false));
    assertSame('https://example.com/?cat=3&paged=2', Canonical::paged('https://example.com/?cat=3', 2, true));
});

test('canonical: only indexable listings get a theme canonical', function (): void {
    assertTrue((new PageContext(PageKind::Term, 'category', 3))->isIndexable());
    assertFalse((new PageContext(PageKind::Search))->isIndexable());
    assertFalse((new PageContext(PageKind::NotFound))->isIndexable());
    assertFalse((new PageContext(PageKind::Singular, 'post', 1, 1, true))->isIndexable());
    assertFalse((new PageContext(PageKind::Singular, 'post', 1, 1, false, false))->isIndexable());
    assertTrue((new PageContext(PageKind::Singular, 'attachment', 9))->isAttachment());
});
