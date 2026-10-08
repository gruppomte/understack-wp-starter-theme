#!/usr/bin/env node
/**
 * Builds an installable theme ZIP in dist/<slug>.zip containing the runtime
 * files, the compiled assets and the production Composer dependencies.
 * No npm or Composer is needed on the production server.
 *
 *   npm run release [-- --keep-stage] [--allow-dirty]
 */
import { ZipArchive } from 'archiver';
import { createHash } from 'node:crypto';
import {
  createWriteStream,
  cpSync,
  existsSync,
  mkdirSync,
  readFileSync,
  rmSync,
  statSync,
} from 'node:fs';
import { basename, join, relative } from 'node:path';
import { commandExists, parseArgs, readIdentity, run, walk } from './lib.mjs';
import { formatReport, scan } from './scan.mjs';

const options = parseArgs(process.argv.slice(2));
const root = process.cwd();
const identity = readIdentity(root);
const dist = join(root, 'dist');
const stage = join(dist, identity.slug);
const zipPath = join(dist, `${identity.slug}.zip`);

const RUNTIME_PATHS = [
  'app',
  'blocks',
  'views',
  'acf-json',
  'config',
  'includes',
  'public',
  'languages',
  'functions.php',
  'index.php',
  'style.css',
  'theme.json',
  'screenshot.png',
  'woocommerce.php',
  'composer.json',
  'composer.lock',
];

const EXCLUDED_FILES = /(^|\/)(\.DS_Store|Thumbs\.db|\.gitkeep|hot|.*\.log|.*\.map)$/;
// Block sources are compiled into public/build; only block.json, render.php and the Twig template ship.
const BLOCK_SOURCES = /^blocks\/[^/]+\/(style|editor)\.css$|^blocks\/[^/]+\/view\.js$/;

function fail(message) {
  console.error(message);
  process.exit(1);
}

function step(title) {
  console.log(`\n== ${title}`);
}

step('Pre-flight');

const pkgVersion = JSON.parse(readFileSync(join(root, 'package.json'), 'utf8')).version;

if (pkgVersion !== identity.version) {
  fail(`Version mismatch: style.css says ${identity.version}, package.json says ${pkgVersion}.`);
}

if (commandExists('git') && existsSync(join(root, '.git'))) {
  const status = run('git', ['status', '--porcelain'], { cwd: root });

  if (status.status === 0 && status.stdout.trim() !== '' && !options['allow-dirty']) {
    fail(
      `The working tree has uncommitted changes; commit them or pass --allow-dirty:\n${status.stdout.trim()}`,
    );
  }
}

step('Production build');

if (!existsSync(join(root, 'node_modules'))) {
  const install = run('npm', ['ci', '--no-audit', '--no-fund'], { cwd: root, inherit: true });
  if (install.status !== 0) fail('npm ci failed.');
}

if (run('npm', ['run', 'build'], { cwd: root, inherit: true }).status !== 0) {
  fail('The production build failed.');
}

if (!existsSync(join(root, 'public/build/.vite/manifest.json'))) {
  fail('public/build/.vite/manifest.json was not generated.');
}

step('Staging runtime files');
rmSync(stage, { recursive: true, force: true });
rmSync(zipPath, { force: true });
mkdirSync(stage, { recursive: true });

for (const path of RUNTIME_PATHS) {
  const source = join(root, path);

  if (!existsSync(source)) continue;

  cpSync(source, join(stage, path), {
    recursive: true,
    filter: (candidate) =>
      candidate === source ||
      (!EXCLUDED_FILES.test(candidate) &&
        !BLOCK_SOURCES.test(relative(root, candidate).split('\\').join('/'))),
  });
}

step('Installing production Composer dependencies');

if (!commandExists('composer')) {
  fail('Composer is required to build the release (it is not needed on the server).');
}

const composer = run(
  'composer',
  [
    'install',
    '--no-dev',
    '--optimize-autoloader',
    '--no-interaction',
    '--prefer-dist',
    '--no-progress',
  ],
  { cwd: stage, inherit: true },
);

if (composer.status !== 0) fail('composer install failed.');

step('Residue scan');
const scanResult = scan(stage, { identity });
console.log(formatReport(scanResult));

if (scanResult.violations.length > 0) fail('Fix the identifiers above before releasing.');

step('Verifying package contents');
const files = walk(stage).map((file) => relative(stage, file).split('\\').join('/'));
const required = [
  'style.css',
  'functions.php',
  'index.php',
  'vendor/autoload.php',
  'public/build/.vite/manifest.json',
  'app/Core/Theme.php',
  'views/layouts/base.twig',
];
const forbidden = [
  /^node_modules\//,
  /^src\//,
  /^tests\//,
  /^scripts\//,
  /^dist\//,
  /\.md$/,
  /^\.env/,
  /^public\/hot$/,
];
const problems = [];

for (const path of required) {
  if (!existsSync(join(stage, path))) problems.push(`missing ${path}`);
}

for (const file of files) {
  if (forbidden.some((pattern) => pattern.test(file))) problems.push(`unexpected ${file}`);
}

if (existsSync(join(stage, 'node_modules'))) problems.push('unexpected node_modules/');
if (problems.length > 0) fail(`Package verification failed:\n  ${problems.join('\n  ')}`);

console.log(`ACF Pro bundled: ${existsSync(join(stage, 'includes/acf/acf.php')) ? 'yes' : 'no'}`);
console.log(`Files: ${files.length}`);

step('Writing ZIP');

await new Promise((resolve, reject) => {
  const output = createWriteStream(zipPath);
  const archive = new ZipArchive({ zlib: { level: 9 } });

  output.on('close', resolve);
  archive.on('error', reject);
  archive.pipe(output);
  archive.directory(stage, basename(stage));
  archive.finalize();
});

if (!options['keep-stage']) {
  rmSync(stage, { recursive: true, force: true });
}

const size = (statSync(zipPath).size / 1024 / 1024).toFixed(2);
const checksum = createHash('sha256').update(readFileSync(zipPath)).digest('hex');
console.log(
  `\nRelease ready: ${relative(root, zipPath)} (${size} MB, version ${identity.version})\nSHA-256: ${checksum}`,
);
