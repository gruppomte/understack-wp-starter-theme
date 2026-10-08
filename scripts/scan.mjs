#!/usr/bin/env node
/**
 * Residue scan: searches the theme's own files for identifiers that must not
 * reach a client project. Third-party code (vendor, node_modules, bundled
 * plugins) is excluded and never modified.
 *
 *   node scripts/scan.mjs [--strict] [--json] [--root <dir>]
 *
 * Without --strict, terms that are part of the theme's current identity
 * (for example the package name of the private starter) are reported as
 * expected instead of failing the scan.
 */
import { readFileSync } from 'node:fs';
import { relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { escapeRegExp, isTextFile, parseArgs, readIdentity, walk } from './lib.mjs';

// The term list is stored encoded so that this file never matches its own scan.
const TERMS = JSON.parse(
  Buffer.from(
    'WyJ1bmRlcnN0YWNrIiwiY2xhdWRlIiwiYW50aHJvcGljIiwiY2hhdGdwdCIsIm9wZW5haSIsImdlbmVyYXRlZCBieSIsImJ1aWx0IGJ5IiwicG93ZXJlZCBieSIsImJvaWxlcnBsYXRlIiwic3RhcnRlciB0aGVtZSJd',
    'base64',
  ).toString('utf8'),
);

const SELF = fileURLToPath(import.meta.url);

/**
 * Development-only files that never ship (release.mjs excludes them) and may
 * name the tools used while developing.
 */
const DEVELOPMENT_ONLY = new Set(['CLAUDE.md', '.env.example']);

/** Development-only directories: documentation never ships and may name third-party crawlers and tools. */
const DEVELOPMENT_ONLY_DIRECTORIES = ['docs/'];

export function scan(root, { strict = false, identity = readIdentity(root) } = {}) {
  const identityValues = [identity.slug, identity.name, identity.namespace, identity.textDomain]
    .filter(Boolean)
    .map((value) => value.toLowerCase());
  const expectedTerms = strict
    ? new Set()
    : new Set(TERMS.filter((term) => identityValues.some((value) => value.includes(term))));

  const matches = [];

  for (const file of walk(root)) {
    const path = relative(root, file).split('\\').join('/');

    if (
      path === 'scripts/scan.mjs' ||
      !isTextFile(file) ||
      DEVELOPMENT_ONLY.has(path) ||
      DEVELOPMENT_ONLY_DIRECTORIES.some((directory) => path.startsWith(directory)) ||
      path.startsWith('.env')
    ) {
      continue;
    }

    for (const term of TERMS) {
      if (new RegExp(escapeRegExp(term), 'i').test(path)) {
        matches.push({
          file: path,
          line: 0,
          term,
          expected: expectedTerms.has(term),
          text: '(file name)',
        });
      }
    }

    const lines = readFileSync(file, 'utf8').split(/\r?\n/);

    lines.forEach((line, index) => {
      for (const term of TERMS) {
        if (new RegExp(escapeRegExp(term), 'i').test(line)) {
          matches.push({
            file: relative(root, file),
            line: index + 1,
            term,
            expected: expectedTerms.has(term),
            text: line.trim().slice(0, 120),
          });
        }
      }
    });
  }

  return {
    identity,
    matches,
    violations: matches.filter((match) => !match.expected),
    expected: matches.filter((match) => match.expected),
  };
}

export function formatReport(result) {
  const lines = [];

  if (result.violations.length === 0) {
    lines.push(`Residue scan passed: no unwanted identifiers in ${result.identity.slug}.`);
  } else {
    lines.push(`Residue scan failed: ${result.violations.length} unwanted identifier(s) found.`);

    for (const match of result.violations) {
      lines.push(`  ${match.file}:${match.line}  [${match.term}]  ${match.text}`);
    }
  }

  if (result.expected.length > 0) {
    const files = new Set(result.expected.map((match) => match.file));
    lines.push(
      `Expected identity matches: ${result.expected.length} in ${files.size} file(s) (current identity "${result.identity.slug}").`,
    );
  }

  return lines.join('\n');
}

if (process.argv[1] === SELF) {
  const options = parseArgs(process.argv.slice(2));
  const root = resolve(options.root ?? process.cwd());
  const result = scan(root, { strict: Boolean(options.strict) });

  if (options.json) {
    console.log(JSON.stringify(result, null, 2));
  } else {
    console.log(formatReport(result));
  }

  process.exit(result.violations.length > 0 ? 1 : 0);
}
