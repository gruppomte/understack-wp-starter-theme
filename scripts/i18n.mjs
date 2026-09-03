#!/usr/bin/env node
/**
 * Generates languages/<text-domain>.pot from the theme's PHP and Twig files.
 *
 *   npm run i18n
 *
 * Only strings that use the theme's text domain are collected. A comment
 * containing "translators:" placed right before a call is kept as a hint.
 */
import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { readIdentity, walk } from './lib.mjs';

const SELF = fileURLToPath(import.meta.url);

/** Argument layout of the WordPress translation functions (null = not a string). */
const FUNCTIONS = {
  __: ['text'],
  _e: ['text'],
  translate: ['text'],
  esc_html__: ['text'],
  esc_html_e: ['text'],
  esc_attr__: ['text'],
  esc_attr_e: ['text'],
  _x: ['text', 'context'],
  _ex: ['text', 'context'],
  esc_html_x: ['text', 'context'],
  esc_attr_x: ['text', 'context'],
  _n: ['text', 'plural', null],
  _nx: ['text', 'plural', null, 'context'],
  _n_noop: ['text', 'plural'],
  _nx_noop: ['text', 'plural', 'context'],
};

const CALL = new RegExp(`(?<![\\w$>.])(${Object.keys(FUNCTIONS).join('|')})\\s*\\(`, 'g');

function unquote(literal) {
  const quote = literal[0];
  const body = literal.slice(1, -1);

  if (quote === "'") {
    return body.replace(/\\(['\\])/g, '$1');
  }

  return body.replace(/\\(["\\$nt])/g, (_, char) => ({ n: '\n', t: '\t' })[char] ?? char);
}

/** Splits the argument list that starts at `index` (just after the opening parenthesis). */
function readArguments(source, index) {
  const args = [];
  let depth = 0;
  let current = '';
  let quote = null;

  for (let i = index; i < source.length; i += 1) {
    const char = source[i];

    if (quote) {
      current += char;
      if (char === '\\') {
        current += source[i + 1] ?? '';
        i += 1;
      } else if (char === quote) {
        quote = null;
      }
      continue;
    }

    if (char === "'" || char === '"') {
      quote = char;
      current += char;
      continue;
    }

    if (char === '(' || char === '[' || char === '{') depth += 1;

    if (char === ')' || char === ']' || char === '}') {
      if (depth === 0) {
        args.push(current.trim());
        return args;
      }
      depth -= 1;
    }

    if (char === ',' && depth === 0) {
      args.push(current.trim());
      current = '';
      continue;
    }

    current += char;
  }

  return null;
}

const asString = (argument) =>
  argument && /^(['"]).*\1$/s.test(argument) ? unquote(argument) : null;

function translatorComment(source, index) {
  const before = source.slice(Math.max(0, index - 400), index);
  const match = before.match(
    /(?:\/\*|\/\/|\{#)\s*(translators:[^*#\n]*?)\s*(?:\*\/|#\}|\n)\s*(?:[^\n]*\n)?\s*$/i,
  );

  return match ? match[1].replace(/\s+/g, ' ').trim() : null;
}

function extract(source, file, textDomain, entries) {
  for (const match of source.matchAll(CALL)) {
    const layout = FUNCTIONS[match[1]];
    const args = readArguments(source, match.index + match[0].length);

    if (!args || args.length < layout.length + 1 || asString(args[layout.length]) !== textDomain) {
      continue;
    }

    const values = {};
    let valid = true;

    layout.forEach((name, position) => {
      if (name === null) return;
      const value = asString(args[position]);
      if (value === null) valid = false;
      values[name] = value;
    });

    if (!valid) continue;

    const key = `${values.context ?? ''}\u0004${values.text}`;
    const line = source.slice(0, match.index).split('\n').length;
    const entry = entries.get(key) ?? { ...values, references: [], comments: new Set() };
    const comment = translatorComment(source, match.index);

    entry.references.push(`${file}:${line}`);
    if (comment) entry.comments.add(comment);
    if (values.plural && !entry.plural) entry.plural = values.plural;
    entries.set(key, entry);
  }
}

const quote = (value) =>
  `"${value.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\t/g, '\\t').replace(/\n/g, '\\n"\n"')}"`;

function render(entries, identity) {
  const lines = [
    `# Translation template for the ${identity.name} theme.`,
    'msgid ""',
    'msgstr ""',
    `"Project-Id-Version: ${identity.name} ${identity.version}\\n"`,
    '"Report-Msgid-Bugs-To: \\n"',
    `"POT-Creation-Date: ${new Date().toISOString().replace(/\.\d+Z$/, '+00:00')}\\n"`,
    '"MIME-Version: 1.0\\n"',
    '"Content-Type: text/plain; charset=UTF-8\\n"',
    '"Content-Transfer-Encoding: 8bit\\n"',
    '"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"',
    '"Last-Translator: \\n"',
    '"Language-Team: \\n"',
    `"X-Domain: ${identity.textDomain}\\n"`,
    '',
  ];

  const sorted = [...entries.values()].sort((a, b) =>
    a.references[0].localeCompare(b.references[0]),
  );

  for (const entry of sorted) {
    for (const comment of entry.comments) lines.push(`#. ${comment}`);
    for (const reference of entry.references) lines.push(`#: ${reference}`);
    if (entry.context) lines.push(`msgctxt ${quote(entry.context)}`);
    lines.push(`msgid ${quote(entry.text)}`);

    if (entry.plural) {
      lines.push(`msgid_plural ${quote(entry.plural)}`, 'msgstr[0] ""', 'msgstr[1] ""');
    } else {
      lines.push('msgstr ""');
    }

    lines.push('');
  }

  return lines.join('\n');
}

export function generatePot(root) {
  const identity = readIdentity(root);
  const entries = new Map();

  for (const file of walk(root)) {
    if (!/\.(php|twig)$/.test(file)) continue;
    extract(
      readFileSync(file, 'utf8'),
      relative(root, file).split('\\').join('/'),
      identity.textDomain,
      entries,
    );
  }

  const directory = join(root, 'languages');
  mkdirSync(directory, { recursive: true });

  for (const file of readdirSync(directory)) {
    if (file.endsWith('.pot')) rmSync(join(directory, file));
  }

  const output = join(directory, `${identity.textDomain}.pot`);
  writeFileSync(output, render(entries, identity));

  return { output: relative(root, output), count: entries.size };
}

if (process.argv[1] === SELF) {
  const root = process.cwd();

  if (!existsSync(join(root, 'style.css'))) {
    console.error('Run this command from the theme directory.');
    process.exit(1);
  }

  const result = generatePot(root);
  console.log(`${result.output}: ${result.count} string(s).`);
}
