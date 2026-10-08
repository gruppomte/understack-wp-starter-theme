#!/usr/bin/env node
/**
 * Head report: what a running site emits for each SEO signal.
 *   node tests/seo-report.mjs --url http://site.local [--label "core only"] [--json]
 *
 * Discovers a page, a post and a category through the REST API, then reads the
 * homepage, those three routes, a paginated listing, search, a 404, robots.txt
 * and the sitemap. Prints a Markdown report (or JSON) listing titles,
 * descriptions, canonicals, robots directives, the X-Robots-Tag header, social
 * metadata, JSON-LD blocks, headings and landmarks, flagging duplicates.
 */
import { parseArgs } from '../scripts/lib.mjs';

const options = parseArgs(process.argv.slice(2));

if (!options.url) {
  console.error('Usage: node tests/seo-report.mjs --url http://site.local [--label text] [--json]');
  process.exit(1);
}

const base = String(options.url).replace(/\/$/, '');
const label = options.label ? String(options.label) : 'head report';

const PROVIDER_MARKERS = [
  ['Rank Math', /Search Engine Optimization by Rank Math/i],
  ['Yoast SEO', /optimized with the Yoast SEO plugin/i],
  ['SEOPress', /SEOPress/i],
  ['All in One SEO', /All in One SEO/i],
  ['The SEO Framework', /The SEO Framework/i],
  ['Slim SEO', /Slim SEO/i],
];

function decode(text) {
  return text
    .replace(/&#(\d+);/g, (match, code) => String.fromCodePoint(Number(code)))
    .replace(/&#x([0-9a-f]+);/gi, (match, code) => String.fromCodePoint(parseInt(code, 16)))
    .replace(/&quot;/g, '"')
    .replace(/&#039;|&apos;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&');
}

function attributes(tag) {
  const found = {};

  for (const match of tag.matchAll(/([a-zA-Z:-]+)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'>]+))/g)) {
    found[match[1].toLowerCase()] = decode(match[2] ?? match[3] ?? match[4] ?? '');
  }

  return found;
}

function inspect(html) {
  const head = html.match(/<head[^>]*>([\s\S]*?)<\/head>/i)?.[1] ?? '';
  const metas = [...head.matchAll(/<meta\b[^>]*>/gi)].map((match) => attributes(match[0]));
  const links = [...head.matchAll(/<link\b[^>]*>/gi)].map((match) => attributes(match[0]));
  const pick = (attribute, value) =>
    metas
      .filter((meta) => (meta[attribute] ?? '').toLowerCase() === value)
      .map((meta) => meta.content ?? '');
  const prefixed = (attribute, prefix) =>
    metas
      .filter((meta) => (meta[attribute] ?? '').toLowerCase().startsWith(prefix))
      .map((meta) => [meta[attribute], meta.content ?? '']);
  const og = prefixed('property', 'og:').concat(prefixed('property', 'article:'));
  const twitter = prefixed('name', 'twitter:');
  const jsonLd = [
    ...head.matchAll(
      /<script\b[^>]*type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi,
    ),
  ].map((match) => {
    try {
      const data = JSON.parse(match[1]);
      const nodes = Array.isArray(data['@graph']) ? data['@graph'] : [data];

      return {
        valid: true,
        types: nodes
          .map((node) => node['@type'])
          .flat()
          .filter(Boolean),
      };
    } catch {
      return { valid: false, types: [] };
    }
  });
  const body = html.replace(/<head[\s\S]*?<\/head>/i, '');

  return {
    title: decode(head.match(/<title[^>]*>([\s\S]*?)<\/title>/i)?.[1] ?? '').trim(),
    descriptions: pick('name', 'description'),
    canonicals: links
      .filter((link) => (link.rel ?? '').toLowerCase() === 'canonical')
      .map((link) => link.href ?? ''),
    robots: pick('name', 'robots'),
    og,
    ogDuplicates: og.length - new Set(og.map(([key]) => key)).size,
    twitter,
    twitterDuplicates: twitter.length - new Set(twitter.map(([key]) => key)).size,
    jsonLd,
    h1: (body.match(/<h1\b/gi) ?? []).length,
    landmarks: ['main', 'nav', 'header', 'footer'].filter((tag) =>
      new RegExp(`<${tag}\\b`, 'i').test(body),
    ),
    providers: PROVIDER_MARKERS.filter(([, pattern]) => pattern.test(head)).map(([name]) => name),
  };
}

async function discover(path) {
  try {
    const response = await fetch(`${base}${path}`);
    const items = await response.json();

    return Array.isArray(items) && items[0]?.link ? items[0].link : null;
  } catch {
    return null;
  }
}

async function route(name, url) {
  const response = await fetch(url, { redirect: 'manual' });
  const html = await response.text();

  return {
    name,
    url,
    status: response.status,
    xRobotsTag: response.headers.get('x-robots-tag') ?? '',
    ...inspect(html),
  };
}

const [page, post, category] = await Promise.all([
  discover('/wp-json/wp/v2/pages?per_page=1&status=publish'),
  discover('/wp-json/wp/v2/posts?per_page=1&status=publish'),
  discover('/wp-json/wp/v2/categories?per_page=1&hide_empty=true'),
]);

const routes = [
  ['home', `${base}/`],
  ['home page 2', `${base}/page/2/`],
  page ? ['page', page] : null,
  post ? ['post', post] : null,
  category ? ['category', category] : null,
  ['search', `${base}/?s=a`],
  ['404', `${base}/${Date.now()}-not-found/`],
].filter(Boolean);

const results = [];

for (const [name, url] of routes) {
  results.push(await route(name, url));
}

const robotsTxt = await fetch(`${base}/robots.txt`).then(async (response) => ({
  status: response.status,
  body: (await response.text()).trim(),
}));
const sitemaps = {};

for (const path of ['/wp-sitemap.xml', '/sitemap_index.xml']) {
  const response = await fetch(`${base}${path}`, { redirect: 'manual' });
  sitemaps[path] =
    `${response.status}${response.headers.get('location') ? ` → ${response.headers.get('location')}` : ''}`;
}

const duplicates = results.flatMap((result) =>
  [
    result.descriptions.length > 1 ? 'description' : null,
    result.canonicals.length > 1 ? 'canonical' : null,
    result.robots.length > 1 ? 'robots' : null,
    result.ogDuplicates > 0 ? 'open graph' : null,
    result.twitterDuplicates > 0 ? 'twitter' : null,
    result.jsonLd.length > 1 ? 'json-ld blocks' : null,
    result.h1 !== 1 ? `h1 (${result.h1})` : null,
  ]
    .filter(Boolean)
    .map((signal) => `${result.name}: ${signal}`),
);

const report = {
  label,
  base,
  generated: new Date().toISOString(),
  routes: results,
  robotsTxt,
  sitemaps,
  duplicates,
};

if (options.json) {
  console.log(JSON.stringify(report, null, 2));
  process.exit(0);
}

const cell = (value) =>
  String(value ?? '')
    .replace(/\|/g, '\\|')
    .replace(/\n/g, ' ');
const lines = [
  `# Head report: ${label}`,
  '',
  `Site: ${base} · Generated: ${report.generated} · Providers seen: ${
    [...new Set(results.flatMap((result) => result.providers))].join(', ') || 'none'
  }`,
  '',
  '| Route | Status | Robots meta | X-Robots-Tag | Description | Canonical | OG | Twitter | JSON-LD | h1 | Landmarks |',
  '| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |',
];

for (const result of results) {
  lines.push(
    `| ${[
      result.name,
      result.status,
      result.robots.join(' / ') || '—',
      result.xRobotsTag || '—',
      result.descriptions.length,
      result.canonicals.length,
      result.og.length,
      result.twitter.length,
      result.jsonLd.length,
      result.h1,
      result.landmarks.join(', '),
    ]
      .map(cell)
      .join(' | ')} |`,
  );
}

lines.push('', '## Details', '');

for (const result of results) {
  lines.push(`### ${result.name} (${result.url})`, '');
  lines.push(`- Title: ${cell(result.title) || '—'}`);
  lines.push(`- Description: ${result.descriptions.map(cell).join(' / ') || '—'}`);
  lines.push(`- Canonical: ${result.canonicals.join(' / ') || '—'}`);
  lines.push(
    `- Open Graph: ${result.og.map(([key, value]) => `${key}=${cell(value)}`).join('; ') || '—'}`,
  );
  lines.push(
    `- Twitter: ${result.twitter.map(([key, value]) => `${key}=${cell(value)}`).join('; ') || '—'}`,
  );
  lines.push(
    `- JSON-LD: ${
      result.jsonLd
        .map((block) => (block.valid ? block.types.join(', ') || 'valid' : 'INVALID JSON'))
        .join(' | ') || '—'
    }`,
  );
  lines.push('');
}

lines.push('## robots.txt', '', '```', robotsTxt.body || `(status ${robotsTxt.status})`, '```', '');
lines.push(
  '## Sitemaps',
  '',
  ...Object.entries(sitemaps).map(([path, status]) => `- ${path}: ${status}`),
  '',
);
lines.push(
  '## Duplicates',
  '',
  ...(duplicates.length ? duplicates.map((item) => `- ${item}`) : ['- none']),
  '',
);

console.log(lines.join('\n'));
