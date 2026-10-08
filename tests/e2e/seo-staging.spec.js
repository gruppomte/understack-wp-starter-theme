import { expect, test } from '@playwright/test';
import { expectNoDuplicates, inspectHead } from './seo-helpers.js';

/**
 * Indexability policy on a copy that declares WP_ENVIRONMENT_TYPE=staging:
 * every page carries noindex, nofollow as a meta directive and as a header,
 * while canonicals and descriptions stay in place and robots.txt is untouched.
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

test('robots.txt is never used to hide a staging site', async ({ request }) => {
  const response = await request.get('/robots.txt');
  const body = await response.text();

  expect(response.status()).toBe(200);
  expect(body).not.toMatch(/^Disallow: \/$/m);
  expect(response.headers()['x-robots-tag']).toBe('noindex, nofollow');
});

test('the core sitemap keeps working on staging', async ({ request }) => {
  const response = await request.get('/wp-sitemap.xml');

  expect(response.status()).toBe(200);
});
