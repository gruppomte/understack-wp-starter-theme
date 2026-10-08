#!/usr/bin/env node
/**
 * Repeated front-end measurements through tests/perf/probe.php (installed as a
 * must-use plugin on a local site):
 *   node tests/perf/measure.mjs --url http://site.local --paths /,/post/ --runs 10 --label "current"
 *
 * Each path is requested runs + 2 times with the probe (the first two warm-up
 * requests are discarded) and once without it to measure the HTML weight.
 * Prints a Markdown table with the median, minimum and maximum of every metric.
 */
import { parseArgs } from '../../scripts/lib.mjs';

const options = parseArgs(process.argv.slice(2));

if (!options.url) {
  console.error(
    'Usage: node tests/perf/measure.mjs --url http://site.local [--paths /,/post/] [--runs 10]',
  );
  process.exit(1);
}

const base = String(options.url).replace(/\/$/, '');
const paths = String(options.paths ?? '/').split(',');
const runs = Number(options.runs ?? 10);
const label = options.label ? String(options.label) : 'measurement';

const median = (values) => {
  const sorted = [...values].sort((a, b) => a - b);
  const middle = Math.floor(sorted.length / 2);

  return sorted.length % 2 ? sorted[middle] : (sorted[middle - 1] + sorted[middle]) / 2;
};

const format = (values, digits = 1) =>
  values.length === 0
    ? '—'
    : `${median(values).toFixed(digits)} (${Math.min(...values).toFixed(digits)}–${Math.max(...values).toFixed(digits)})`;

const rows = [];

for (const path of paths) {
  const samples = [];

  for (let run = 0; run < runs + 2; run += 1) {
    const response = await fetch(`${base}${path}${path.includes('?') ? '&' : '?'}__perf=1`, {
      headers: { 'Cache-Control': 'no-cache' },
    });
    const html = await response.text();
    const match = html.match(/<!--__perf (\{.*?\}) -->/);

    if (!match) {
      console.error(
        `No probe output for ${path} (status ${response.status}); is the probe installed?`,
      );
      process.exit(1);
    }

    if (run >= 2) samples.push(JSON.parse(match[1]));
  }

  const plain = await fetch(`${base}${path}`);
  const bytes = Buffer.byteLength(await plain.text(), 'utf8');
  const metric = (key, digits) =>
    format(
      samples.map((sample) => sample[key]).filter((value) => value !== null),
      digits,
    );

  rows.push({
    path,
    status: plain.status,
    bytes,
    total: metric('total_ms'),
    queries: metric('queries', 0),
    duplicates: metric('duplicate_queries', 0),
    memory: metric('peak_memory_kb', 0),
    hooks: metric('seo_hooks', 0),
    boot: metric('boot_ms', 2),
    head: metric('head_ms', 2),
    jsonld: metric('jsonld_ms', 2),
    http: metric('http_requests', 0),
  });
}

console.log(`### ${label} (${runs} samples per path after 2 warm-ups)`);
console.log('');
console.log(
  '| Path | Status | HTML bytes | Total ms | Queries | Duplicate queries | Peak memory KB | SEO hooks | Boot ms | Head ms | JSON-LD ms | HTTP requests |',
);
console.log('| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |');

for (const row of rows) {
  console.log(
    `| ${[row.path, row.status, row.bytes, row.total, row.queries, row.duplicates, row.memory, row.hooks, row.boot, row.head, row.jsonld, row.http].join(' | ')} |`,
  );
}

console.log('');
console.log(JSON.stringify({ label, base, runs, rows }));
