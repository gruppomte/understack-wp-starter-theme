<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Config;
use Understack\Seo\Crawlers\Groups;
use Understack\Seo\Identity\Identity;
use Understack\Seo\StructuredData\Author;
use Understack\Seo\StructuredData\Graph;
use Understack\Seo\StructuredData\Input;
use Understack\Seo\StructuredData\Serializer;
use Understack\Seo\Text;

$huge = str_repeat('A', 100000);
$binary = "\x00\x01\x02\xff\xfe<script>alert(1)</script>\r\n";

test('hardening: identity survives huge, binary and wrongly typed input', function () use ($huge, $binary): void {
    $identity = Identity::fromArray([
        'type' => $huge,
        'name' => $huge,
        'legal_name' => $binary,
        'description' => $huge,
        'logo' => $huge,
        'url' => $binary,
        'email' => $huge . '@example.com',
        'telephone' => $huge,
        'address' => $huge,
        'areas_served' => array_fill(0, 500, $binary),
        'same_as' => array_fill(0, 500, 'javascript:alert(1)'),
    ]);

    assertSame('', $identity->type);
    assertSame(200, mb_strlen($identity->name));
    assertSame(500, mb_strlen($identity->description));
    assertSame([], $identity->logo);
    assertSame('', $identity->url);
    assertSame('', $identity->email);
    assertSame('', $identity->telephone);
    // Invalid UTF-8 is dropped rather than propagated.
    assertSame([], $identity->areasServed);
    assertSame([], $identity->sameAs);
    assertFalse(str_contains($identity->legalName, '<'));
    assertFalse(str_contains($identity->legalName, "\x00"));
    assertTrue(count($identity->issues) > 0);
});

test('hardening: identity with nested arrays where strings are expected', function (): void {
    $identity = Identity::fromArray([
        'type' => ['Organization'],
        'name' => ['x' => 'y'],
        'email' => ['info@example.com'],
        'same_as' => [['https://example.com'], 42, null, true],
        'address' => ['street' => ['x'], 'country' => ['IT']],
    ]);

    assertSame('', $identity->type);
    assertSame('', $identity->name);
    assertSame('', $identity->email);
    assertSame([], $identity->sameAs);
    assertSame('', $identity->address['street']);
    assertSame('', $identity->address['country']);
});

test('hardening: configuration ignores oversized and wrongly typed values', function () use ($huge): void {
    $config = Config::fromArray([
        'enabled' => 'true',
        'provider' => $huge,
        'with_provider' => $huge,
        'indexability' => ['production_hosts' => array_fill(0, 1000, 'example.com'), 'post_types' => [$huge]],
        'social' => ['twitter_site' => $huge, 'image_size' => '../../etc/passwd', 'default_image' => '12'],
        'breadcrumbs' => ['home_label' => "<b>Home</b>\n"],
        'crawlers' => ['policy' => 'custom', 'groups' => array_fill(0, 60, ['agents' => ['Bot'], 'disallow' => ['/']])],
        'content' => ['article_post_types' => 'post'],
    ]);

    assertTrue($config->bool('enabled'));
    assertSame('auto', $config->string('provider'));
    assertSame([], $config->list('with_provider'));
    assertSame(['example.com'], $config->list('indexability.production_hosts'));
    assertSame([], $config->list('indexability.post_types'));
    assertSame('', $config->string('social.twitter_site'));
    assertSame('wide', $config->string('social.image_size'));
    assertSame(0, $config->int('social.default_image'));
    assertSame('', $config->string('breadcrumbs.home_label'));
    assertSame([], $config->get('crawlers.groups'));
    assertSame(['post'], $config->list('content.article_post_types'));
    assertTrue(count($config->issues()) >= 9, describe($config->issues()));
});

test('hardening: crawler groups reject injection attempts', function (): void {
    foreach (["/\r\nUser-agent: *", "/\nAllow: /", '/<script>', '/ private', 'http://evil.example/'] as $path) {
        assertSame(null, Groups::normalise([['agents' => ['Bot'], 'disallow' => [$path]]]), $path);
    }

    assertSame(null, Groups::normalise([['agents' => ["Bot\nDisallow: /"], 'disallow' => ['/']]]));
    // Surrounding whitespace is trimmed, not rejected.
    assertSame('/', Groups::normalise([['agents' => ['Bot'], 'disallow' => ["/ \n"]]])[0]['disallow'][0]);
});

test('hardening: summaries of huge content are bounded and fast', function () use ($huge): void {
    $start = microtime(true);
    $summary = Text::summary(str_repeat('<p>Word ' . $huge . '</p>', 20), 160);
    $elapsed = microtime(true) - $start;

    assertTrue(mb_strlen($summary) <= 160);
    assertTrue($elapsed < 1.0, 'took ' . round($elapsed, 3) . 's');
});

test('hardening: the graph never leaks account data even when it is handed in', function (): void {
    $author = Author::fromUser([
        'id' => 9,
        'display_name' => 'Giulia Verdi',
        'user_login' => 'gverdi',
        'user_email' => 'giulia@example.com',
        'user_pass' => 'secret-hash',
        'user_activation_key' => 'key',
    ]);
    $json = Serializer::encode(Graph::build(new Input(
        home: 'https://example.com',
        siteName: 'Site',
        canonical: 'https://example.com/post/',
        article: [
            'type' => 'BlogPosting',
            'headline' => 'Post',
            'published' => '',
            'modified' => '',
            'sections' => [],
            'wordCount' => 0,
        ],
        author: $author,
    )));

    foreach (['giulia@', 'gverdi', 'secret-hash', 'user_pass', 'activation'] as $needle) {
        assertFalse(str_contains($json, $needle), $needle);
    }

    assertStringContains('"name":"Giulia Verdi"', $json);
});

test('hardening: malicious strings cannot break out of the JSON-LD script', function (): void {
    $identity = Identity::fromArray([
        'type' => 'Organization',
        'name' => '</script><script>alert(1)</script>Alfa',
        'description' => '"; alert(2); //',
        'same_as' => ['https://example.com/?q=</script>'],
    ]);
    $json = Serializer::encode(
        Graph::build(new Input(home: 'https://example.com', siteName: '</script>', identity: $identity))
    );

    assertFalse(str_contains($json, '</script'));
    assertFalse(str_contains($json, '<'));
    assertStringContains('"name":"Alfa"', $json);
    assertSame(true, json_decode($json, true) !== null);
});
