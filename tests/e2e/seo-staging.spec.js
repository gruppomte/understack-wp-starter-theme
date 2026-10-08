/* global getComputedStyle */
import { expect, test } from '@playwright/test';
import {
  expectNoDuplicates,
  inspectBreadcrumbs,
  inspectHead,
  inspectJsonLd,
} from './seo-helpers.js';

/**
 * Indexability policy on a copy that declares WP_ENVIRONMENT_TYPE=staging:
 * every page carries noindex, nofollow as a meta directive and as a header,
 * while canonicals and descriptions stay in place and robots.txt is untouched.
 * This Playground also runs with tests/playground/seo-features.php, which
 * enables breadcrumbs and author profiles.
 */
for (const path of ['/', '/sample-post-1/', '/about/', '/category/news/']) {
  test(`${path} is not indexable on staging`, async ({ page }) => {
    const head = await inspectHead(page, path);

    expect(head.status).toBe(200);
    expectNoDuplicates(expect, head);
    expect(head.robots).toEqual(['noindex, nofollow']);
    expect(head.headers['x-robots-tag']).toBe('noindex, nofollow');
    expect(head.canonicals).toHaveLength(1);
  });
}

test('staging keeps the description and social fallbacks', async ({ page }) => {
  const head = await inspectHead(page, '/sample-post-1/');

  expect(head.descriptions).toEqual(['Body of post 1.']);
  expect(head.og['og:type']).toBe('article');
});

test('staging 404 pages carry the header too', async ({ page }) => {
  const head = await inspectHead(page, `/no-such-page-${Date.now()}/`);

  expect(head.status).toBe(404);
  expect(head.robots).toEqual(['noindex, nofollow']);
  expect(head.headers['x-robots-tag']).toBe('noindex, nofollow');
});

test('robots.txt keeps the core block and appends the configured crawler groups', async ({
  request,
  baseURL,
}) => {
  const response = await request.get('/robots.txt');
  const body = await response.text();

  expect(response.status()).toBe(200);
  expect(response.headers()['x-robots-tag']).toBe('noindex, nofollow');
  expect(body).toMatch(
    /^User-agent: \*\nDisallow: \/wp-admin\/\nAllow: \/wp-admin\/admin-ajax\.php/,
  );
  expect(body).toContain(`Sitemap: ${baseURL}/wp-sitemap.xml`);
  expect(body).toMatch(
    /\n\nUser-agent: ExampleTrainingBot\nDisallow: \/\n\nUser-agent: \*\nDisallow: \/private\/\nCrawl-delay: 5\n$/,
  );
  expect(body.indexOf('Sitemap:')).toBeLessThan(body.indexOf('ExampleTrainingBot'));
});

test('the core sitemap keeps working on staging', async ({ request }) => {
  const response = await request.get('/wp-sitemap.xml');

  expect(response.status()).toBe(200);
});

test.describe('breadcrumbs enabled', () => {
  test('a nested page shows its hierarchy and the matching BreadcrumbList', async ({
    page,
    baseURL,
  }) => {
    await page.goto('/about/team/');
    const trail = await inspectBreadcrumbs(page);

    expect(trail).toEqual([
      { name: 'Home', url: `${baseURL}/`, current: false },
      { name: 'About us', url: `${baseURL}/about/`, current: false },
      { name: 'Team', url: null, current: true },
    ]);
    await expect(page.locator('nav[aria-label="Breadcrumb"] ol')).toHaveCount(1);

    const graph = await inspectJsonLd(page);
    const list = graph.byType('BreadcrumbList');
    expect(
      list.itemListElement.map((item) => [item.position, item.name, item.item ?? null]),
    ).toEqual([
      [1, 'Home', `${baseURL}/`],
      [2, 'About us', `${baseURL}/about/`],
      [3, 'Team', null],
    ]);
    // The WPML mock types this page as ContactPage; the page node is found by its identifier.
    const pageNode = graph.nodes.find((node) => node['@id']?.endsWith('#webpage'));
    expect(pageNode.breadcrumb).toEqual({ '@id': list['@id'] });
    expect(graph.dangling).toEqual([]);
  });

  test('a post shows its category and a paginated category shows the page number', async ({
    page,
    baseURL,
  }) => {
    await page.goto('/sample-post-2/');
    expect((await inspectBreadcrumbs(page)).map((item) => item.name)).toEqual([
      'Home',
      'News',
      'Sample post 2 with an apostrophe\u2019s entity',
    ]);

    await page.goto('/category/news/page/2/');
    expect(await inspectBreadcrumbs(page)).toEqual([
      { name: 'Home', url: `${baseURL}/`, current: false },
      { name: 'News', url: `${baseURL}/category/news/`, current: false },
      { name: 'Page 2', url: null, current: true },
    ]);
  });

  test('the front page has no trail', async ({ page }) => {
    await page.goto('/');

    expect(await inspectBreadcrumbs(page)).toBeNull();
  });

  test('author archives expose a profile only for a public name', async ({ page, baseURL }) => {
    await page.goto('/author/alice/');
    const alice = await inspectJsonLd(page);
    const person = alice.byType('Person');

    expect(alice.types).toContain('ProfilePage');
    expect(alice.byType('ProfilePage').mainEntity).toEqual({ '@id': person['@id'] });
    expect(person.name).toBe('Alice Rossi');
    expect(person.url).toBe(`${baseURL}/author/alice/`);
    expect((await inspectBreadcrumbs(page)).map((item) => item.name)).toEqual([
      'Home',
      'Alice Rossi',
    ]);

    await page.goto('/author/admin/');
    const admin = await inspectJsonLd(page);
    expect(admin.types).not.toContain('ProfilePage');
    expect(admin.types).not.toContain('Person');
    expect(admin.raw).not.toContain('"admin"');
    expect((await inspectBreadcrumbs(page)).map((item) => item.name)).toEqual(['Home', 'Author']);
  });

  test('the post author links to the archive when profiles are enabled', async ({
    page,
    baseURL,
  }) => {
    await page.goto('/sample-post-2/');
    const graph = await inspectJsonLd(page);

    expect(graph.byType('Person').url).toBe(`${baseURL}/author/alice/`);
  });
});

test.describe('WPML mock', () => {
  test('a configured page ID is mapped to its translation before typing the page', async ({
    page,
  }) => {
    await page.goto('/about/team/');
    const graph = await inspectJsonLd(page);

    expect(graph.types).toContain('ContactPage');
    expect(graph.types).not.toContain('WebPage');

    await page.goto('/contact/');
    expect((await inspectJsonLd(page)).types).toContain('ContactPage');
  });

  test('the theme emits no hreflang links of its own', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('link[rel="alternate"][hreflang]')).toHaveCount(0);
  });
});

test.describe('breadcrumb accessibility', () => {
  test('the trail is a labelled navigation with an ordered list, a current item and visible focus', async ({
    page,
  }) => {
    await page.goto('/about/team/');

    const nav = page.locator('nav[aria-label="Breadcrumb"]');
    await expect(nav).toHaveCount(1);
    await expect(nav.locator('ol > li')).toHaveCount(3);
    await expect(nav.locator('[aria-current="page"]')).toHaveText('Team');
    await expect(nav.locator('a')).toHaveCount(2);

    const link = nav.locator('a').first();
    await link.focus();
    await expect(link).toBeFocused();
    const visible = await link.evaluate((element) => {
      const style = getComputedStyle(element);

      return style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0;
    });
    expect(visible).toBe(true);

    const separators = await nav.locator('[aria-hidden="true"]').allTextContents();
    expect(separators.every((text) => text.trim() === '/')).toBe(true);
  });
});
