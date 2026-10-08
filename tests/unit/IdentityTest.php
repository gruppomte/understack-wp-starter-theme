<?php

declare(strict_types=1);

namespace Understack\Tests\Unit;

use Understack\Seo\Identity\Identity;
use Understack\Seo\Identity\Types;

$complete = [
    'type' => 'Dentist',
    'name' => ' Studio <b>Alfa</b> ',
    'legal_name' => 'Studio Alfa S.r.l.',
    'description' => "<p>Dental care</p>\n<p>since 1990.</p>",
    'logo' => ['url' => 'https://example.com/logo.png', 'width' => 400, 'height' => 400],
    'url' => 'https://example.com/',
    'email' => 'info@example.com',
    'telephone' => '+39 02 1234567',
    'address' => [
        'street' => 'Via Roma 1',
        'locality' => 'Milano',
        'region' => 'MI',
        'postal_code' => '20100',
        'country' => 'it',
    ],
    'areas_served' => ['Milano', 'Monza', 'Milano', ''],
    'same_as' => ['https://example.com/profile', 'https://example.com/profile', 'https://it.wikipedia.org/wiki/Alfa'],
];

test('identity: complete data is sanitised and kept', function () use ($complete): void {
    $identity = Identity::fromArray($complete);

    assertTrue($identity->isConfigured());
    assertSame('Dentist', $identity->schemaType());
    assertSame('Studio Alfa', $identity->name);
    assertSame('Dental care since 1990.', $identity->description);
    assertSame('IT', $identity->address['country']);
    assertSame(['Milano', 'Monza'], $identity->areasServed);
    assertSame(['https://example.com/profile', 'https://it.wikipedia.org/wiki/Alfa'], $identity->sameAs);
    assertSame(400, $identity->logo['width'] ?? 0);
    assertSame([], $identity->issues);
});

test('identity: nothing configured produces nothing', function (): void {
    $identity = Identity::fromArray([]);

    assertFalse($identity->isConfigured());
    assertSame('', $identity->schemaType());
    assertSame([], $identity->issues);
});

test('identity: a LocalBusiness type without a full address becomes Organization', function () use ($complete): void {
    $identity = Identity::fromArray(['address' => ['locality' => 'Milano']] + $complete);

    assertSame('Dentist', $identity->type);
    assertSame('Organization', $identity->schemaType());
    assertStringContains('Dentist needs a street, a locality and a country', $identity->issues[0]);
    assertTrue($identity->hasAnyAddress());
    assertFalse($identity->hasPostalAddress());
});

test('identity: an address alone never turns an Organization into a LocalBusiness', function () use ($complete): void {
    assertSame('Organization', Identity::fromArray(['type' => 'Organization'] + $complete)->schemaType());
});

test('identity: unknown types and missing names are reported', function (): void {
    $unknown = Identity::fromArray(['type' => 'Shop', 'name' => 'Alfa']);
    assertSame('', $unknown->type);
    assertFalse($unknown->isConfigured());
    assertStringContains('Unknown organisation type "Shop"', $unknown->issues[0]);

    $nameless = Identity::fromArray(['type' => 'Organization', 'name' => '<script>x</script>']);
    assertFalse($nameless->isConfigured());
    assertStringContains('name is missing', $nameless->issues[0]);
});

test('identity: invalid contact data, logo and profile URLs are dropped with an issue each', function (): void {
    $identity = Identity::fromArray([
        'type' => 'Organization',
        'name' => 'Alfa',
        'url' => 'example.com',
        'email' => 'not-an-email',
        'telephone' => 'call me',
        'logo' => ['url' => 'javascript:alert(1)', 'width' => 0, 'height' => 0],
        'address' => ['country' => 'Italy'],
        'same_as' => ['ftp://example.com', 'https://example.com/ok'],
    ]);

    assertSame('', $identity->url);
    assertSame('', $identity->email);
    assertSame('', $identity->telephone);
    assertSame([], $identity->logo);
    assertSame('', $identity->address['country']);
    assertSame(['https://example.com/ok'], $identity->sameAs);
    assertSame(6, count($identity->issues), describe($identity->issues));
});

test('identity: a small logo is kept but reported', function (): void {
    $identity = Identity::fromArray([
        'type' => 'Organization',
        'name' => 'Alfa',
        'logo' => ['url' => 'https://example.com/l.png', 'width' => 64, 'height' => 64],
    ]);

    assertSame('https://example.com/l.png', $identity->logo['url'] ?? '');
    assertStringContains('112x112', $identity->issues[0]);
});

test('identity: same_as accepts one URL per line', function (): void {
    $identity = Identity::fromArray(['same_as' => "https://a.example/\nhttps://b.example/\n"]);

    assertSame(['https://a.example/', 'https://b.example/'], $identity->sameAs);
});

test('identity: the type list is verified and LocalBusiness detection covers descendants', function (): void {
    assertTrue(in_array('Restaurant', Types::all(), true));
    assertTrue(Types::isLocalBusiness('Restaurant'));
    assertTrue(Types::isLocalBusiness('LocalBusiness'));
    assertFalse(Types::isLocalBusiness('Corporation'));
    assertFalse(Types::isLocalBusiness('Organization'));
    assertSame(count(Types::all()), count(array_unique(Types::all())));
});
