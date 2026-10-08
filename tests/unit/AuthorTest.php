<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\StructuredData\Author;

test('author: a public display name becomes a person without any account data', function (): void {
    $author = Author::fromUser([
        'id' => 7,
        'display_name' => 'Maria Rossi',
        'user_login' => 'mrossi',
        'user_nicename' => 'mrossi',
        'user_email' => 'maria@example.com',
        'description' => '<p>Writes about <b>things</b>.</p>',
        'user_url' => 'https://example.com/maria',
    ]);

    assertSame([
        'id' => 7,
        'name' => 'Maria Rossi',
        'description' => 'Writes about things.',
        'url' => 'https://example.com/maria',
    ], $author);
});

test('author: e-mail-like display names are rejected, never converted', function (): void {
    $names = [
        'maria@example.com',
        'maria @ example',
        'Maria <maria@example.com>',
        'https://example.com',
        'maria/rossi',
    ];

    foreach ($names as $name) {
        assertSame(null, Author::fromUser(['display_name' => $name]), $name);
    }
});

test('author: technical identifiers are never exposed', function (): void {
    $user = ['user_login' => 'admin', 'user_nicename' => 'admin-2', 'user_email' => 'editor@example.com'];

    assertSame(null, Author::fromUser(['display_name' => 'admin'] + $user));
    assertSame(null, Author::fromUser(['display_name' => 'Admin'] + $user));
    assertSame(null, Author::fromUser(['display_name' => 'admin-2'] + $user));
    assertSame(null, Author::fromUser(['display_name' => 'editor'] + $user));
    assertSame(null, Author::fromUser(['display_name' => 'editor@example.com'] + $user));
    assertSame('Site Editor', Author::fromUser(['display_name' => 'Site Editor'] + $user)['name'] ?? null);
});

test('author: empty, too short or symbol-only names are rejected', function (): void {
    foreach (['', ' ', 'M', '42', '--', "\t"] as $name) {
        assertSame(null, Author::fromUser(['display_name' => $name]), $name === '' ? 'empty' : $name);
    }
});

test('author: only web URLs are kept and the biography is plain text', function (): void {
    $author = Author::fromUser([
        'display_name' => 'Luca Bianchi',
        'user_url' => 'javascript:alert(1)',
        'description' => "Line one\n\nLine <em>two</em>",
    ]);

    assertSame('', $author['url'] ?? null);
    assertSame('Line one Line two', $author['description'] ?? null);
    $ftp = Author::fromUser(['display_name' => 'Luca Bianchi', 'user_url' => 'ftp://example.com']);
    assertSame('', $ftp['url'] ?? null);
});
