import { expect, test } from '@playwright/test';
import { inspectBreadcrumbs, inspectHead, inspectJsonLd } from './seo-helpers.js';

/**
 * SEO/GEO matrix, staging Playground: WP_ENVIRONMENT_TYPE=staging plus the
 * tests/playground/seo-features.php fixture (breadcrumbs, author profiles,
 * custom crawler policy, WPML mock, a "portfolio" post type with a "genre"
 * taxonomy). Every page is noindex, nofollow with the matching header while
 * every other signal stays as in production.
 */
const cases = [
  {
    name: 'home',
    path: '/',
    description: 0,
    canonical: 'self',
    breadcrumbs: null,
    graph: ['Organization', 'WebSite', 'CollectionPage'],
  },
  {
    name: 'post',
    path: '/sample-post-2/',
    description: 1,
    canonical: 'self',
    breadcrumbs: ['Home', 'News', 'Sample post 2 with an apostrophe’s entity'],
    graph: ['Organization', 'WebSite', 'BreadcrumbList', 'Person', 'WebPage', 'BlogPosting'],
  },
  {
    name: 'nested page',
    path: '/about/team/',
    description: 1,
    canonical: 'self',
    breadcrumbs: ['Home', 'About us', 'Team'],
    graph: ['Organization', 'WebSite', 'BreadcrumbList', 'ContactPage'],
  },
  {
    name: 'category page 2',
    path: '/category/news/page/2/',
    description: 0,
    canonical: 'self',
    breadcrumbs: ['Home', 'News', 'Page 2'],
    graph: ['Organization', 'WebSite', 'BreadcrumbList', 'CollectionPage'],
  },
  {
    name: 'tag',
    path: '/tag/sample/',
    description: 0,
    canonical: 'self',
    breadcrumbs: ['Home', 'sample'],
    graph: ['Organization', 'WebSite', 'BreadcrumbList', 'CollectionPage'],
  },
  {
    name: 'custom post type archive',
    path: '/portfolio/',
    description: 1,
    canonical: 'self',
    breadcrumbs: ['Home', 'Portfolio'],
    graph: ['Organization', 'WebSite', 'BreadcrumbList', 'CollectionPage'],
  },
  {
    name: 'custom post type single',
    path: '/portfolio/portfolio-item-1/',
    description: 1,
    canonical: 'self',
    breadcrumbs: ['Home', 'Portfolio', 'Portfolio item 1'],
    graph: ['Organization', 'WebSite', 'BreadcrumbList', 'WebPage'],
  },
  {
    name: 'custom taxonomy term',
    path: '/genre/branding/',
    description: 0,
    canonical: 'self',
    breadcrumbs: ['Home', 'Branding'],
    graph: ['Organization', 'WebSite', 'BreadcrumbList', 'CollectionPage'],
  },
  {
    name: 'author profile',
    path: '/author/alice/',
    description: 1,
    canonical: 'self',
    breadcrumbs: ['Home', 'Alice Rossi'],
    graph: ['Organization', 'WebSite', 'BreadcrumbList', 'Person', 'ProfilePage'],
  },
  {
    name: 'search',
    path: '/?s=sample',
    description: 0,
    canonical: 'none',
    breadcrumbs: null,
    graph: null,
  },
  {
    name: '404',
    path: '/matrix-missing-page/',
    description: 0,
    canonical: 'none',
    breadcrumbs: null,
    graph: null,
  },
];

for (const item of cases) {
  test(`staging · ${item.name}`, async ({ page, baseURL }) => {
    const head = await inspectHead(page, item.path);

    expect(head.status).toBe(item.name === '404' ? 404 : 200);
    expect(head.robots).toEqual(['noindex, nofollow']);
    expect(head.headers['x-robots-tag']).toBe('noindex, nofollow');
    expect(head.descriptions).toHaveLength(item.description);
    expect(head.canonicals).toHaveLength(item.canonical === 'self' ? 1 : 0);
    if (item.canonical === 'self')
      expect(head.canonicals[0]).toBe(`${baseURL}${item.path.split('?')[0]}`);
    expect(head.ogDuplicates + head.twitterDuplicates).toBe(0);
    expect(head.h1).toBe(1);

    const trail = await inspectBreadcrumbs(page);
    if (item.breadcrumbs === null) {
      expect(trail).toBeNull();
    } else {
      expect(trail.map((crumb) => crumb.name)).toEqual(item.breadcrumbs);
      expect(trail.at(-1).current).toBe(true);
    }

    const graph = await inspectJsonLd(page);
    if (item.graph === null) {
      expect(graph).toBeNull();
    } else {
      expect(graph.types).toEqual(item.graph);
      expect(graph.dangling).toEqual([]);
      if (item.breadcrumbs) {
        const list = graph.byType('BreadcrumbList');
        expect(list.itemListElement.map((entry) => entry.name)).toEqual(item.breadcrumbs);
      }
    }
  });
}

test('staging · the custom post type is in the core sitemap and the policy is in robots.txt', async ({
  request,
}) => {
  const sitemap = await request.get('/wp-sitemap.xml');
  expect(sitemap.status()).toBe(200);
  expect(await sitemap.text()).toContain('wp-sitemap-posts-portfolio-1.xml');

  const robots = await request.get('/robots.txt');
  expect(await robots.text()).toContain('User-agent: ExampleTrainingBot');
});
