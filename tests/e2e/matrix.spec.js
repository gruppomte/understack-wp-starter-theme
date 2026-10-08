import { expect, test } from '@playwright/test';
import { inspectBreadcrumbs, inspectHead, inspectJsonLd } from './seo-helpers.js';

/**
 * SEO/GEO matrix, production Playground (no SEO plugin, defaults): one row per
 * content class with the expected state of every signal. The staging rows live
 * in matrix-staging.spec.js; Rank Math, Yoast and WooCommerce rows are measured
 * on the local site (docs/seo-geo/08-head-reports). A skipped case is never a pass.
 */
const cases = [
  {
    name: 'home (posts on front)',
    path: '/',
    status: 200,
    description: 0,
    canonical: 'self',
    robots: 'index',
    social: true,
    graph: ['Organization', 'WebSite', 'CollectionPage'],
  },
  {
    name: 'home page 2',
    path: '/page/2/',
    status: 200,
    description: 0,
    canonical: 'self',
    robots: 'index',
    social: true,
    graph: ['Organization', 'WebSite', 'CollectionPage'],
  },
  {
    name: 'page',
    path: '/about/',
    status: 200,
    description: 1,
    canonical: 'self',
    robots: 'index',
    social: true,
    graph: ['Organization', 'WebSite', 'AboutPage'],
  },
  {
    name: 'nested page',
    path: '/about/team/',
    status: 200,
    description: 1,
    canonical: 'self',
    robots: 'index',
    social: true,
    graph: ['Organization', 'WebSite', 'WebPage'],
  },
  {
    name: 'post with public author',
    path: '/sample-post-2/',
    status: 200,
    description: 1,
    canonical: 'self',
    robots: 'index',
    social: true,
    graph: ['Organization', 'WebSite', 'Person', 'WebPage', 'BlogPosting'],
  },
  {
    name: 'post without public author',
    path: '/sample-post-1/',
    status: 200,
    description: 1,
    canonical: 'self',
    robots: 'index',
    social: true,
    graph: ['Organization', 'WebSite', 'WebPage', 'BlogPosting'],
  },
  {
    name: 'category',
    path: '/category/news/',
    status: 200,
    description: 0,
    canonical: 'self',
    robots: 'index',
    social: true,
    graph: ['Organization', 'WebSite', 'CollectionPage'],
  },
  {
    name: 'category page 2',
    path: '/category/news/page/2/',
    status: 200,
    description: 0,
    canonical: 'self',
    robots: 'index',
    social: true,
    graph: ['Organization', 'WebSite', 'CollectionPage'],
  },
  {
    name: 'tag',
    path: '/tag/sample/',
    status: 200,
    description: 0,
    canonical: 'self',
    robots: 'index',
    social: true,
    graph: ['Organization', 'WebSite', 'CollectionPage'],
  },
  {
    name: 'author archive (public name, profiles off)',
    path: '/author/alice/',
    status: 200,
    description: 1,
    canonical: 'self',
    robots: 'index',
    social: true,
    graph: ['Organization', 'WebSite', 'CollectionPage'],
  },
  {
    name: 'search',
    path: '/?s=sample',
    status: 200,
    description: 0,
    canonical: 'none',
    robots: 'noindex',
    social: false,
    graph: null,
  },
  {
    name: '404',
    path: '/matrix-missing-page/',
    status: 404,
    description: 0,
    canonical: 'none',
    robots: 'noindex',
    social: false,
    graph: null,
  },
];

for (const item of cases) {
  test(`production · ${item.name}`, async ({ page, baseURL }) => {
    const head = await inspectHead(page, item.path);

    expect(head.status).toBe(item.status);
    expect(head.descriptions).toHaveLength(item.description);
    expect(head.canonicals).toHaveLength(item.canonical === 'self' ? 1 : 0);
    if (item.canonical === 'self')
      expect(head.canonicals[0]).toBe(`${baseURL}${item.path.split('?')[0]}`);
    expect(head.robots).toHaveLength(1);
    expect(head.robots[0].includes('noindex')).toBe(item.robots === 'noindex');
    expect(head.headers['x-robots-tag']).toBeUndefined();
    expect(Object.keys(head.og).length > 0).toBe(item.social);
    expect(Object.keys(head.twitter).length > 0).toBe(item.social);
    expect(head.ogDuplicates + head.twitterDuplicates).toBe(0);
    expect(head.h1).toBe(1);
    expect(await inspectBreadcrumbs(page)).toBeNull();
    await expect(page.locator('link[rel="alternate"][hreflang]')).toHaveCount(0);

    const graph = await inspectJsonLd(page);
    if (item.graph === null) {
      expect(graph).toBeNull();
    } else {
      expect(graph.types).toEqual(item.graph);
      expect(graph.duplicateIds).toEqual([]);
      expect(graph.dangling).toEqual([]);
    }
  });
}

test('production · sitemap and robots.txt', async ({ request, baseURL }) => {
  const sitemap = await request.get('/wp-sitemap.xml');
  expect(sitemap.status()).toBe(200);
  expect(await sitemap.text()).toContain('wp-sitemap-posts-post-1.xml');

  const robots = await request.get('/robots.txt');
  const body = await robots.text();
  expect(robots.status()).toBe(200);
  expect(body.trim()).toBe(
    `User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: ${baseURL}/wp-sitemap.xml`,
  );
});
