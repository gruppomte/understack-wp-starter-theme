#!/usr/bin/env node
/**
 * Validation suite: PHP syntax, Composer configuration, build output, linters,
 * residue scan and, when WordPress is reachable, the runtime smoke tests.
 *
 *   npm run check [-- --build] [--url http://site.local] [--no-wp] [--no-lint]
 */
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { commandExists, parseArgs, run, walk } from './lib.mjs';
import { formatReport, scan } from './scan.mjs';

const options = parseArgs(process.argv.slice(2));
const root = process.cwd();
const results = [];

function record(name, ok, detail = '', skipped = false) {
  results.push({ name, ok, detail, skipped });
  const status = skipped ? 'skip' : ok ? ' ok ' : 'FAIL';
  console.log(`[${status}] ${name}${detail ? ` - ${detail}` : ''}`);
}

function phpFiles() {
  return walk(root).filter(
    (file) => file.endsWith('.php') && !file.includes('/vendor/') && !file.includes('/includes/'),
  );
}

// 1. PHP syntax
if (commandExists('php')) {
  const failures = [];

  for (const file of phpFiles()) {
    const result = run('php', ['-l', file]);
    if (result.status !== 0) failures.push(result.stdout.trim() || result.stderr.trim());
  }

  record('PHP syntax', failures.length === 0, failures.join('\n') || `${phpFiles().length} files`);
} else {
  record('PHP syntax', false, 'php not found', true);
}

// 2. Composer
if (commandExists('composer')) {
  const result = run('composer', ['validate', '--no-check-publish', '--strict'], { cwd: root });
  record(
    'Composer configuration',
    result.status === 0,
    result.status === 0 ? '' : result.stderr.trim(),
  );
} else {
  record('Composer configuration', false, 'composer not found', true);
}

record('Composer autoloader', existsSync(join(root, 'vendor/autoload.php')), 'vendor/autoload.php');

// 3. Build
if (options.build) {
  const result = run('npm', ['run', 'build'], { cwd: root, inherit: true });
  record('Production build', result.status === 0);
}

const manifestPath = join(root, 'public/build/.vite/manifest.json');

if (existsSync(manifestPath)) {
  const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
  const missing = ['src/css/app.css', 'src/js/app.js', 'src/css/editor.css'].filter(
    (entry) => !manifest[entry] || !existsSync(join(root, 'public/build', manifest[entry].file)),
  );
  record(
    'Vite manifest',
    missing.length === 0,
    missing.length ? `missing ${missing.join(', ')}` : `${Object.keys(manifest).length} entries`,
  );
} else {
  record('Vite manifest', false, 'run "npm run build" first');
}

// 4. Linters
if (options.lint !== false) {
  const eslint = run('node', [join(root, 'node_modules/eslint/bin/eslint.js'), '.'], { cwd: root });
  record(
    'ESLint',
    eslint.status === 0,
    eslint.status === 0 ? '' : (eslint.stdout + eslint.stderr).trim(),
  );

  const phpcsBin = join(root, 'vendor/squizlabs/php_codesniffer/bin/phpcs');

  if (existsSync(phpcsBin) && commandExists('php')) {
    const phpcs = run('php', [phpcsBin, '--no-colors', '-q'], { cwd: root });
    record('PHP_CodeSniffer', phpcs.status === 0, phpcs.status === 0 ? '' : phpcs.stdout.trim());
  } else {
    record('PHP_CodeSniffer', false, 'phpcs not installed', true);
  }
}

// 5. Residue scan
const scanResult = scan(root, { strict: Boolean(options.strict) });
record('Residue scan', scanResult.violations.length === 0, formatReport(scanResult).split('\n')[0]);

// 6. WordPress runtime smoke test
function findWordPress() {
  let directory = root;

  for (let depth = 0; depth < 6; depth += 1) {
    if (existsSync(join(directory, 'wp-load.php'))) return join(directory, 'wp-load.php');
    directory = dirname(directory);
  }

  return null;
}

const wpLoad = findWordPress();

if (options.wp === false || !wpLoad) {
  record('WordPress smoke test', false, wpLoad ? 'disabled' : 'wp-load.php not found', true);
} else if (commandExists('php')) {
  const result = run('php', [join(root, 'tests/smoke.php'), wpLoad], { cwd: root });
  console.log(
    (result.stdout + result.stderr)
      .trim()
      .split('\n')
      .map((line) => `       ${line}`)
      .join('\n'),
  );
  record('WordPress smoke test', result.status === 0);
}

// 7. HTTP smoke test
if (options.url) {
  const result = run('node', [join(root, 'tests/http.mjs'), '--url', options.url], { cwd: root });
  console.log(
    (result.stdout + result.stderr)
      .trim()
      .split('\n')
      .map((line) => `       ${line}`)
      .join('\n'),
  );
  record('HTTP smoke test', result.status === 0);
} else {
  record('HTTP smoke test', false, 'pass --url to enable', true);
}

const failed = results.filter((result) => !result.ok && !result.skipped);
console.log(
  `\n${failed.length === 0 ? 'All checks passed.' : `${failed.length} check(s) failed.`}`,
);
process.exit(failed.length === 0 ? 0 : 1);
