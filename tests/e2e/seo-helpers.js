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
