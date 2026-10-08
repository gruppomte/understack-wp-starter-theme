/* global document */

/**
 * Reads the SEO-relevant head output of the current page in one evaluation.
 */
export async function inspectHead(page, path) {
  const response = await page.goto(path);
  const head = await page.evaluate(() => {
    const all = (selector) => [...document.querySelectorAll(selector)];
    const pairs = (selector, attribute) =>
      all(selector).map((element) => [element.getAttribute(attribute), element.content]);
    const og = pairs('meta[property^="og:"], meta[property^="article:"]', 'property');
    const twitter = pairs('meta[name^="twitter:"]', 'name');

    return {
      title: document.title,
      descriptions: all('meta[name="description"]').map((meta) => meta.content),
      canonicals: all('link[rel="canonical"]').map((link) => link.href),
      robots: all('meta[name="robots"]').map((meta) => meta.content),
      og: Object.fromEntries(og),
      ogDuplicates: og.length - new Set(og.map(([key]) => key)).size,
      twitter: Object.fromEntries(twitter),
      twitterDuplicates: twitter.length - new Set(twitter.map(([key]) => key)).size,
      jsonLd: all('script[type="application/ld+json"]').length,
      h1: all('h1').length,
    };
  });

  return { status: response?.status(), headers: response?.headers() ?? {}, ...head };
}

/** Fails on any duplicated head signal. */
export function expectNoDuplicates(expect, head) {
  expect(head.descriptions.length, 'descriptions').toBeLessThanOrEqual(1);
  expect(head.canonicals.length, 'canonicals').toBeLessThanOrEqual(1);
  expect(head.robots.length, 'robots').toBeLessThanOrEqual(1);
  expect(head.ogDuplicates, 'Open Graph duplicates').toBe(0);
  expect(head.twitterDuplicates, 'Twitter duplicates').toBe(0);
  expect(head.h1, 'h1 count').toBe(1);
}

/**
 * Parses the JSON-LD block of the current page (null when absent, throws on
 * invalid JSON) and lists its nodes, duplicate identifiers and dangling references.
 */
export async function inspectJsonLd(page) {
  const scripts = await page.locator('script[type="application/ld+json"]').allTextContents();

  if (scripts.length === 0) return null;
  if (scripts.length > 1) throw new Error(`${scripts.length} JSON-LD blocks found`);

  const data = JSON.parse(scripts[0]);
  const nodes = Array.isArray(data['@graph']) ? data['@graph'] : [data];
  const declared = [];
  const referenced = [];
  const walk = (value) => {
    if (!value || typeof value !== 'object') return;
    if (Array.isArray(value)) {
      value.forEach(walk);
      return;
    }
    if (typeof value['@id'] === 'string') {
      (Object.keys(value).length === 1 ? referenced : declared).push(value['@id']);
    }
    for (const [key, item] of Object.entries(value)) if (key !== '@id') walk(item);
  };
  nodes.forEach(walk);

  const byType = (type) => nodes.find((node) => node['@type'] === type) ?? null;

  return {
    raw: scripts[0],
    context: data['@context'],
    nodes,
    types: nodes.map((node) => node['@type']),
    byType,
    duplicateIds: declared.filter((id, index) => declared.indexOf(id) !== index),
    dangling: referenced.filter((id) => !declared.includes(id)),
  };
}

/** The breadcrumb trail as rendered: names and links, in order. */
export async function inspectBreadcrumbs(page) {
  const nav = page.locator('nav[aria-label="Breadcrumb"]');

  if ((await nav.count()) === 0) return null;

  return nav.locator('li').evaluateAll((items) =>
    items.map((item) => {
      const link = item.querySelector('a');
      const current = item.querySelector('[aria-current="page"]');

      return {
        name: (link ?? current ?? item).textContent.trim(),
        url: link ? link.href : null,
        current: Boolean(current),
      };
    }),
  );
}
