#!/usr/bin/env node
/**
 * HTTP smoke test against a running site:
 *   node tests/http.mjs --url http://site.local
 *
 * Discovers a page, a post and a category through the REST API and checks the
 * homepage, search and 404 responses for errors, layout landmarks and assets.
 */
import { parseArgs } from '../scripts/lib.mjs';

const options = parseArgs(process.argv.slice(2));

if (!options.url) {
  console.error('Usage: node tests/http.mjs --url http://site.local');
  process.exit(1);
}

const base = String(options.url).replace(/\/$/, '');
const errorPattern =
  /(Fatal error|Warning|Notice|Deprecated)<\/b>:|(Fatal error|Warning|Notice|Deprecated): .+ in .+ on line \d+/;
let failures = 0;

async function discover(path) {
  try {
    const response = await fetch(`${base}${path}`);
    const items = await response.json();
    return Array.isArray(items) && items[0]?.link ? items[0].link : null;
  } catch {
    return null;
  }
}

async function check(label, url, expectedStatus) {
  const response = await fetch(url, { redirect: 'manual' });
  const body = await response.text();
  const problems = [];

  if (response.status !== expectedStatus)
    problems.push(`status ${response.status} (expected ${expectedStatus})`);
  if (errorPattern.test(body)) problems.push('PHP error output');
  if (!body.includes('id="main-content"')) problems.push('main landmark missing');
  if (!body.includes('skip-link')) problems.push('skip link missing');
  if (!body.includes('public/build/assets/')) problems.push('compiled assets missing');
  if (body.includes('localhost:5173') || body.includes('@vite/client'))
    problems.push('dev server reference');

  if (problems.length > 0) failures += 1;
  console.log(
    `[${problems.length === 0 ? ' ok ' : 'FAIL'}] ${label} ${url}${problems.length ? ` - ${problems.join(', ')}` : ''}`,
  );
}

const [page, post, category] = await Promise.all([
  discover('/wp-json/wp/v2/pages?per_page=1&status=publish'),
  discover('/wp-json/wp/v2/posts?per_page=1&status=publish'),
  discover('/wp-json/wp/v2/categories?per_page=1&hide_empty=true'),
]);

await check('homepage', `${base}/`, 200);
if (page) await check('page', page, 200);
if (post) await check('single', post, 200);
if (category) await check('archive', category, 200);
await check('search', `${base}/?s=a`, 200);
await check('404', `${base}/${Date.now()}-not-found/`, 404);

for (const [label, value] of [
  ['page', page],
  ['single', post],
  ['archive', category],
]) {
  if (!value) console.log(`[skip] ${label} - nothing published`);
}

console.log(failures === 0 ? 'HTTP smoke test passed.' : `${failures} route(s) failed.`);
process.exit(failures === 0 ? 0 : 1);
