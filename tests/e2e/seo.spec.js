import { expect, test } from '@playwright/test';
import { expectNoDuplicates, inspectHead } from './seo-helpers.js';

/**
 * Head output of the SEO foundation without any SEO plugin on a production
 * environment (the default Playground). See docs/seo-geo/03-configuration.md.
 */
const external = Boolean(process.env.BASE_URL);

test.skip(
  external && process.env.SEO_ENVIRONMENT !== 'production',
  'Set SEO_ENVIRONMENT=production to run the SEO checks against an external site.',
);

test('home: one canonical, social fallback, no description without content', async ({
  page,
  baseURL,
}) => {
  const head = await inspectHead(page, '/');

  expect(head.status).toBe(200);
  expectNoDuplicates(expect, head);
  expect(head.canonicals).toEqual([`${baseURL}/`]);
  expect(head.robots).toEqual(['max-image-preview:large']);
  expect(head.headers['x-robots-tag']).toBeUndefined();
  expect(head.descriptions).toEqual([]);
  expect(head.og['og:type']).toBe('website');
  expect(head.og['og:url']).toBe(`${baseURL}/`);
  expect(head.og['og:site_name']).toBe('Playground');
  expect(head.og['og:image']).toBeUndefined();
  expect(head.twitter['twitter:card']).toBe('summary');
  expect(head.jsonLd).toBe(0);
});

test('paginated listing: canonical points to its own page and carries no description', async ({
  page,
  baseURL,
}) => {
  const head = await inspectHead(page, '/page/2/');

  expect(head.status).toBe(200);
  expectNoDuplicates(expect, head);
  expect(head.canonicals).toEqual([`${baseURL}/page/2/`]);
  expect(head.descriptions).toEqual([]);
});

test('post: core canonical, description from content, article metadata', async ({
  page,
  baseURL,
}) => {
  const head = await inspectHead(page, '/sample-post-1/');

  expect(head.status).toBe(200);
  expectNoDuplicates(expect, head);
  expect(head.canonicals).toEqual([`${baseURL}/sample-post-1/`]);
  expect(head.descriptions).toEqual(['Body of post 1.']);
  expect(head.og['og:type']).toBe('article');
  // WordPress texturises the title (curly apostrophe); the theme decodes entities for meta content.
  expect(head.og['og:title']).toContain('Sample post 1 with an apostrophe\u2019s entity');
  expect(head.og['og:description']).toBe('Body of post 1.');
  expect(head.og['article:published_time']).toMatch(/^\d{4}-\d{2}-\d{2}T/);
  expect(head.twitter['twitter:title']).toBe(head.og['og:title']);
});

test('post: an explicit comment page keeps its own canonical', async ({ page }) => {
  const head = await inspectHead(page, '/sample-post-1/comment-page-2/');

  expect(head.status).toBe(200);
  expect(head.canonicals).toHaveLength(1);
  expect(head.canonicals[0]).toContain('/sample-post-1/comment-page-2/');
});

test('page: description summarises the content without markup', async ({ page }) => {
  const head = await inspectHead(page, '/about/');

  expect(head.status).toBe(200);
  expectNoDuplicates(expect, head);
  expect(head.descriptions).toHaveLength(1);
  expect(head.descriptions[0]).toMatch(
    /^Who we are A short introduction with a link and bold text\./,
  );
  expect(head.descriptions[0]).not.toMatch(/<|wp:/);
  expect(head.og['og:type']).toBe('website');
});

test('category archive: theme canonical, no description without a term description', async ({
  page,
  baseURL,
}) => {
  const head = await inspectHead(page, '/category/news/');

  expect(head.status).toBe(200);
  expectNoDuplicates(expect, head);
  expect(head.canonicals).toEqual([`${baseURL}/category/news/`]);
  expect(head.title).toMatch(/^News/);
  expect(head.descriptions).toEqual([]);
  expect(head.robots).toEqual(['max-image-preview:large']);
});

test('search results: noindex from core, nothing else from the theme', async ({ page }) => {
  const head = await inspectHead(page, '/?s=sample');

  expect(head.status).toBe(200);
  expectNoDuplicates(expect, head);
  expect(head.robots[0]).toContain('noindex');
  expect(head.canonicals).toEqual([]);
  expect(head.descriptions).toEqual([]);
  expect(Object.keys(head.og)).toEqual([]);
});

test('404: noindex from the theme, no canonical or social metadata', async ({ page }) => {
  const head = await inspectHead(page, `/no-such-page-${Date.now()}/`);

  expect(head.status).toBe(404);
  expectNoDuplicates(expect, head);
  expect(head.robots[0]).toContain('noindex');
  expect(head.robots[0]).not.toContain('nofollow');
  expect(head.canonicals).toEqual([]);
  expect(Object.keys(head.og)).toEqual([]);
});

test('robots.txt stays the core file with the sitemap line', async ({ request, baseURL }) => {
  const response = await request.get('/robots.txt');
  const body = await response.text();

  expect(response.status()).toBe(200);
  expect(body).toContain('Disallow: /wp-admin/');
  expect(body).toContain(`Sitemap: ${baseURL}/wp-sitemap.xml`);
  expect(body).not.toMatch(/^Disallow: \/$/m);
});

test('core sitemap is served without the users sitemap', async ({ request }) => {
  const response = await request.get('/wp-sitemap.xml');
  const body = await response.text();

  expect(response.status()).toBe(200);
  expect(body).toContain('wp-sitemap-posts-post-1.xml');
  expect(body).toContain('wp-sitemap-posts-page-1.xml');
  expect(body).not.toContain('wp-sitemap-users-');
});
