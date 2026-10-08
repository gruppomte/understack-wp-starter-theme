import { expect, test } from '@playwright/test';
import {
  expectNoDuplicates,
  inspectBreadcrumbs,
  inspectHead,
  inspectJsonLd,
} from './seo-helpers.js';

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
  expect(head.jsonLd).toBe(1);
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

test.describe('structured data', () => {
  test('home: one graph with the organisation, the website and a collection page', async ({
    page,
    baseURL,
  }) => {
    await page.goto('/');
    const graph = await inspectJsonLd(page);

    expect(graph?.context).toBe('https://schema.org');
    expect(graph.types).toEqual(['Organization', 'WebSite', 'CollectionPage']);
    expect(graph.duplicateIds).toEqual([]);
    expect(graph.dangling).toEqual([]);

    const organization = graph.byType('Organization');
    expect(organization['@id']).toBe(`${baseURL}/#organization`);
    expect(organization.name).toBe('Playground Organisation');
    expect(organization.legalName).toBe('Playground Organisation Ltd');
    expect(organization.address).toMatchObject({
      '@type': 'PostalAddress',
      addressLocality: 'London',
      addressCountry: 'GB',
    });
    expect(organization.sameAs).toEqual([
      'https://social.example/playground',
      'https://www.wikidata.org/wiki/Q42',
    ]);
    expect(organization.areaServed).toEqual(['London', 'United Kingdom']);
    expect(organization.logo).toMatchObject({
      '@type': 'ImageObject',
      '@id': `${baseURL}/#logo`,
      width: 300,
      height: 300,
    });
    expect(organization.image).toEqual({ '@id': `${baseURL}/#logo` });
    expect(organization).not.toHaveProperty('aggregateRating');

    expect(graph.byType('WebSite').publisher).toEqual({ '@id': `${baseURL}/#organization` });
    expect(graph.byType('CollectionPage').isPartOf).toEqual({ '@id': `${baseURL}/#website` });
    expect(graph.byType('CollectionPage')).not.toHaveProperty('breadcrumb');
  });

  test('post by the administrator: article without a person because the display name is a login', async ({
    page,
    baseURL,
  }) => {
    await page.goto('/sample-post-1/');
    const graph = await inspectJsonLd(page);

    expect(graph.types).toEqual(['Organization', 'WebSite', 'WebPage', 'BlogPosting']);
    expect(graph.dangling).toEqual([]);

    const article = graph.byType('BlogPosting');
    expect(article['@id']).toBe(`${baseURL}/sample-post-1/#article`);
    expect(article.headline).toBe('Sample post 1 with an apostrophe\u2019s entity');
    expect(article.datePublished).toMatch(/^\d{4}-\d{2}-\d{2}T/);
    expect(article.publisher).toEqual({ '@id': `${baseURL}/#organization` });
    expect(article).not.toHaveProperty('author');
    expect(article.articleSection).toEqual(['News']);
    expect(graph.raw).not.toContain('admin@');
    expect(graph.raw).not.toContain('"admin"');
  });

  test('post by a public author: person node without any account data or archive link', async ({
    page,
  }) => {
    await page.goto('/sample-post-2/');
    const graph = await inspectJsonLd(page);
    const person = graph.byType('Person');

    expect(graph.types).toEqual(['Organization', 'WebSite', 'Person', 'WebPage', 'BlogPosting']);
    expect(graph.dangling).toEqual([]);
    expect(person.name).toBe('Alice Rossi');
    expect(person.description).toBe('Writes the sample posts.');
    expect(person.sameAs).toEqual(['https://alice.example']);
    expect(person).not.toHaveProperty('url');
    expect(graph.byType('BlogPosting').author).toEqual({ '@id': person['@id'] });
    expect(graph.raw).not.toContain('alice@');
    expect(graph.raw).not.toContain('"alice"');
  });

  test('about and contact pages are typed through the editorial field only', async ({
    page,
    baseURL,
  }) => {
    await page.goto('/about/');
    const about = await inspectJsonLd(page);
    expect(about.types).toEqual(['Organization', 'WebSite', 'AboutPage']);
    expect(about.byType('AboutPage').about).toEqual({ '@id': `${baseURL}/#organization` });

    await page.goto('/contact/');
    const contact = await inspectJsonLd(page);
    expect(contact.types).toEqual(['Organization', 'WebSite', 'ContactPage']);

    await page.goto('/about/team/');
    const team = await inspectJsonLd(page);
    expect(team.types).toEqual(['Organization', 'WebSite', 'WebPage']);
  });

  test('search results and 404 pages carry no graph', async ({ page }) => {
    await page.goto('/?s=sample');
    expect(await inspectJsonLd(page)).toBeNull();

    await page.goto(`/no-such-page-${Date.now()}/`);
    expect(await inspectJsonLd(page)).toBeNull();
  });
});

test.describe('breadcrumbs disabled by default', () => {
  for (const path of ['/about/team/', '/sample-post-2/', '/category/news/']) {
    test(`${path} has no breadcrumb trail and an unchanged header`, async ({ page }) => {
      await page.goto(path);

      expect(await inspectBreadcrumbs(page)).toBeNull();
      await expect(page.locator('header.wrap nav')).toHaveCount(0);
      expect(await page.locator('header.wrap h1, header.wrap > div > h1').count()).toBe(1);
      expect((await inspectJsonLd(page)).types).not.toContain('BreadcrumbList');
    });
  }
});

test('the theme emits no hreflang links without a multilingual plugin', async ({ page }) => {
  await page.goto('/sample-post-1/');

  await expect(page.locator('link[rel="alternate"][hreflang]')).toHaveCount(0);
});
