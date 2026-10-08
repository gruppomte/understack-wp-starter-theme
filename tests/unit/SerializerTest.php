<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\StructuredData\Serializer;

test('serializer: empty values are pruned recursively, zero and false survive', function (): void {
    $pruned = Serializer::prune([
        'name' => 'Site',
        'empty' => '',
        'nothing' => null,
        'list' => ['', null, 'kept', []],
        'nested' => ['deep' => ['gone' => '']],
        'zero' => 0,
        'flag' => false,
    ]);

    assertSame(['name' => 'Site', 'list' => ['kept'], 'zero' => 0, 'flag' => false], $pruned);
});

test('serializer: the payload cannot close the script element', function (): void {
    $json = Serializer::encode([['@type' => 'WebSite', 'name' => "</script><script>alert('x')</script> & \"quotes\""]]);

    assertFalse(str_contains($json, '</script>'));
    assertFalse(str_contains($json, '<'));
    assertStringContains(chr(92) . 'u003C/script' . chr(92) . 'u003E', $json);
    assertStringContains('"@context":"https://schema.org"', $json);
    assertStringContains('<script type="application/ld+json">', Serializer::script($json));
    assertSame('', Serializer::script(''));
});

test('serializer: unicode and slashes stay readable', function (): void {
    $json = Serializer::encode([
        ['@type' => 'Organization', 'name' => 'Società Àlfa', 'url' => 'https://example.com/'],
    ]);

    assertStringContains('"name":"Società Àlfa"', $json);
    assertStringContains('"url":"https://example.com/"', $json);
});
