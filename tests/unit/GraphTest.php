<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Identity\Identity;
use Understack\Seo\StructuredData\Graph;
use Understack\Seo\StructuredData\Input;
use Understack\Seo\StructuredData\Serializer;

// Finds the first node of a type in a graph.
$node = static function (array $nodes, string $type): ?array {
    foreach ($nodes as $candidate) {
        if (($candidate['@type'] ?? '') === $type) {
            return $candidate;
        }
    }

    return null;
};

$identity = Identity::fromArray([
    'type' => 'Restaurant',
    'name' => 'Trattoria Esempio',
    'legal_name' => 'Trattoria Esempio S.n.c.',
    'description' => 'Cucina di casa.',
    'logo' => ['url' => 'https://example.com/logo.png', 'width' => 400, 'height' => 400],
    'email' => 'info@example.com',
    'telephone' => '+39 02 1234567',
    'address' => ['street' => 'Via Roma 1', 'locality' => 'Milano', 'postal_code' => '20100', 'country' => 'IT'],
    'areas_served' => ['Milano'],
    'same_as' => ['https://example.com/profile'],
]);

$postInput = new Input(
    home: 'https://example.com',
    siteName: 'Esempio',
    language: 'it-IT',
    tagline: 'Un sito',
    identity: $identity,
    canonical: 'https://example.com/news/post/',
    pageType: 'WebPage',
    title: 'Post – Esempio',
    description: 'Summary of the post.',
    datePublished: '2026-01-02T10:00:00+01:00',
    dateModified: '2026-01-03T10:00:00+01:00',
    article: [
        'type' => 'BlogPosting',
        'headline' => 'Post',
        'published' => '2026-01-02T10:00:00+01:00',
        'modified' => '2026-01-03T10:00:00+01:00',
        'sections' => ['News'],
        'wordCount' => 120,
    ],
    author: ['id' => 3, 'name' => 'Maria Rossi', 'description' => 'Writer.', 'url' => 'https://maria.example'],
    authorUrl: 'https://example.com/author/maria/',
    image: ['url' => 'https://example.com/img.jpg', 'width' => 1600, 'height' => 900, 'alt' => 'Alt'],
    breadcrumbs: [
        ['name' => 'Home', 'url' => 'https://example.com/'],
        ['name' => 'News', 'url' => 'https://example.com/news/'],
        ['name' => 'Post', 'url' => null],
    ],
);

test('graph: a complete post produces linked nodes with stable identifiers', function () use ($postInput, $node): void {
    $nodes = Graph::build($postInput);
    $types = array_map(static fn (array $n): string => (string) $n['@type'], $nodes);

    assertSame(['Restaurant', 'WebSite', 'ImageObject', 'BreadcrumbList', 'Person', 'WebPage', 'BlogPosting'], $types);
    assertSame([], Graph::danglingReferences($nodes));
    assertSame(count($nodes), count(array_unique(array_column($nodes, '@id'))));

    $article = $node($nodes, 'BlogPosting');
    assertSame('https://example.com/news/post/#article', $article['@id']);
    assertSame(['@id' => 'https://example.com/news/post/#webpage'], $article['mainEntityOfPage']);
    assertSame(['@id' => 'https://example.com/#organization'], $article['publisher']);
    assertSame(['@id' => 'https://example.com/news/post/#primaryimage'], $article['image']);
    assertSame($node($nodes, 'Person')['@id'], $article['author']['@id']);
    assertSame('2026-01-02T10:00:00+01:00', $article['datePublished']);

    $page = $node($nodes, 'WebPage');
    assertSame(['@id' => 'https://example.com/#website'], $page['isPartOf']);
    assertSame(['@id' => 'https://example.com/news/post/#breadcrumb'], $page['breadcrumb']);
    assertSame(['@id' => 'https://example.com/#organization'], $node($nodes, 'WebSite')['publisher']);
});

test('graph: the organisation node carries only configured data', function () use ($postInput, $node): void {
    $organization = $node(Graph::build($postInput), 'Restaurant');

    assertSame('Trattoria Esempio', $organization['name']);
    assertSame('Trattoria Esempio S.n.c.', $organization['legalName']);
    assertSame('https://example.com/', $organization['url']);
    assertSame('PostalAddress', $organization['address']['@type']);
    assertSame('IT', $organization['address']['addressCountry']);
    assertSame(['https://example.com/profile'], $organization['sameAs']);
    assertSame('https://example.com/#logo', $organization['logo']['@id']);
    assertFalse(array_key_exists('aggregateRating', $organization));
});

test('graph: without an identity there is no organisation and no publisher', function () use ($postInput, $node): void {
    $input = new Input(
        home: $postInput->home,
        siteName: $postInput->siteName,
        canonical: $postInput->canonical,
        article: $postInput->article,
        author: null
    );
    $nodes = Graph::build($input);

    assertSame(null, $node($nodes, 'Restaurant'));
    assertSame(null, $node($nodes, 'Organization'));
    assertFalse(array_key_exists('publisher', Serializer::prune($node($nodes, 'WebSite'))));
    assertFalse(array_key_exists('publisher', Serializer::prune($node($nodes, 'BlogPosting'))));
    assertFalse(array_key_exists('author', Serializer::prune($node($nodes, 'BlogPosting'))));
    assertSame(null, $node($nodes, 'Person'));
    assertSame([], Graph::danglingReferences($nodes));
});

test('graph: a LocalBusiness type without an address is emitted as Organization', function () use ($postInput): void {
    $partial = Identity::fromArray([
        'type' => 'Restaurant',
        'name' => 'Trattoria',
        'address' => ['locality' => 'Milano'],
    ]);
    $nodes = Graph::build(new Input(home: $postInput->home, siteName: 'Esempio', identity: $partial));

    assertSame('Organization', $nodes[0]['@type']);
    assertSame('Milano', $nodes[0]['address']['addressLocality']);
});

test('graph: about and contact pages are typed only when told so', function () use ($postInput, $node): void {
    $about = Graph::build(new Input(
        home: $postInput->home,
        siteName: 'Esempio',
        identity: $postInput->identity,
        canonical: 'https://example.com/about/',
        pageType: 'AboutPage',
        title: 'About'
    ));
    $aboutPage = $node($about, 'AboutPage');
    assertSame(['@id' => 'https://example.com/#organization'], $aboutPage['about']);

    $contact = Graph::build(new Input(
        home: $postInput->home,
        siteName: 'Esempio',
        canonical: 'https://example.com/contact/',
        pageType: 'ContactPage',
        title: 'Contact'
    ));
    assertSame('ContactPage', $node($contact, 'ContactPage')['@type']);
    assertFalse(array_key_exists('about', Serializer::prune($node($contact, 'ContactPage'))));

    $plain = Graph::build(new Input(
        home: $postInput->home,
        siteName: 'Esempio',
        canonical: 'https://example.com/other/',
        title: 'Other'
    ));
    assertSame('WebPage', $node($plain, 'WebPage')['@type']);
});

test('graph: no page nodes without a canonical URL', function () use ($postInput): void {
    $nodes = Graph::build(new Input(home: $postInput->home, siteName: 'Esempio', identity: $postInput->identity));

    assertSame(['Restaurant', 'WebSite'], array_column($nodes, '@type'));
});

test('graph: breadcrumb list mirrors the trail, current page unlinked', function () use ($postInput, $node): void {
    $list = $node(Graph::build($postInput), 'BreadcrumbList');
    $elements = Serializer::prune($list)['itemListElement'];

    assertSame(3, count($elements));
    assertSame([1, 2, 3], array_column($elements, 'position'));
    assertSame('https://example.com/news/', $elements[1]['item']);
    assertFalse(array_key_exists('item', $elements[2]));
    assertSame('Post', $elements[2]['name']);
});

test('graph: a single breadcrumb item produces no list', function () use ($postInput, $node): void {
    $input = new Input(
        home: $postInput->home,
        siteName: 'Esempio',
        canonical: $postInput->canonical,
        breadcrumbs: [['name' => 'Home', 'url' => 'https://example.com/']]
    );
    $nodes = Graph::build($input);

    assertSame(null, $node($nodes, 'BreadcrumbList'));
    assertFalse(array_key_exists('breadcrumb', Serializer::prune($node($nodes, 'WebPage'))));
});

test('graph: the author profile page uses the person as main entity', function () use ($postInput, $node): void {
    $input = new Input(
        home: $postInput->home,
        siteName: 'Esempio',
        canonical: 'https://example.com/author/maria/',
        pageType: 'ProfilePage',
        title: 'Maria Rossi',
        profile: ['id' => 3, 'name' => 'Maria Rossi', 'description' => 'Writer.', 'url' => ''],
        profileUrl: 'https://example.com/author/maria/',
    );
    $nodes = Graph::build($input);
    $person = $node($nodes, 'Person');

    assertSame($person['@id'], $node($nodes, 'ProfilePage')['mainEntity']['@id']);
    assertSame('https://example.com/author/maria/', $person['url']);
    assertSame([], Graph::danglingReferences($nodes));
});

test('graph: the payload has no empty properties and no account data', function () use ($postInput): void {
    $json = Serializer::encode(Graph::build($postInput));
    $decoded = json_decode($json, true);
    $empty = [];
    $walk = static function (mixed $value, string $path) use (&$walk, &$empty): void {
        if (is_array($value)) {
            if ($value === []) {
                $empty[] = $path;
            }

            foreach ($value as $key => $item) {
                $walk($item, $path . '/' . $key);
            }
        } elseif ($value === null || $value === '') {
            $empty[] = $path;
        }
    };
    $walk($decoded, '');

    assertSame([], $empty);
    assertFalse(str_contains($json, 'maria@'));
    assertFalse(str_contains($json, 'user_login'));
    assertStringContains('"wordCount":120', $json);
});

test('graph: dangling references are detected', function (): void {
    $nodes = [[
        '@type' => 'WebPage',
        '@id' => 'https://example.com/#webpage',
        'isPartOf' => ['@id' => 'https://example.com/#website'],
    ]];

    assertSame(['https://example.com/#website'], Graph::danglingReferences($nodes));
});
