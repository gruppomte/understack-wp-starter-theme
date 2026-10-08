<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Text;

test('text: plain strips block comments, tags, scripts and entities', function (): void {
    $html = "<!-- wp:paragraph --><p>Hello&nbsp;<strong>world</strong> &amp; friends.</p><!-- /wp:paragraph -->"
        . "<script>alert(1)</script><style>p{}</style><!-- wp:acf/hero {\"data\":{\"heading\":\"Hidden\"}} /-->"
        . "<ul><li>One</li><li>Two</li></ul>";

    assertSame('Hello world & friends. One Two', Text::plain($html));
});

test('text: short summaries are returned untouched', function (): void {
    assertSame('A short sentence.', Text::summary('<p>A short sentence.</p>'));
    assertSame('', Text::summary('<!-- wp:spacer /-->   '));
});

test('text: long summaries end at a sentence when one falls late enough', function (): void {
    $text = str_repeat('Word ', 20) . 'ends here. ' . str_repeat('More ', 30);

    $summary = Text::summary($text, 120);

    assertSame(str_repeat('Word ', 20) . 'ends here.', $summary);
});

test('text: otherwise the cut lands on a word boundary with an ellipsis', function (): void {
    $summary = Text::summary(str_repeat('abcdef ', 40), 50);

    assertSame(49, mb_strlen($summary));
    assertTrue(str_ends_with($summary, "\u{2026}"));
    assertFalse(str_contains(rtrim($summary, "\u{2026}"), '  '));
});

test('text: summaries never exceed the limit', function (): void {
    foreach ([str_repeat('x', 500), str_repeat('verylongwordwithoutspaces', 20), str_repeat('a b ', 200)] as $text) {
        assertTrue(mb_strlen(Text::summary($text, 160)) <= 160, $text);
    }
});

test('text: host extraction lowercases and drops ports and paths', function (): void {
    assertSame('www.example.com', Text::host('https://WWW.Example.com:8443/path?x=1'));
    assertSame('example.com', Text::host('Example.COM'));
    assertSame('staging.example.com', Text::host('staging.example.com/'));
    assertSame('', Text::host(''));
});
