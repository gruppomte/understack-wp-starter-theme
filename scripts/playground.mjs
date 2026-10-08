#!/usr/bin/env node
/**
 * Runs the theme in a disposable WordPress Playground (PHP in WebAssembly, no
 * Docker, no database server) with sample content from tests/playground/seed.php.
 * Used by the end-to-end tests locally and in CI.
 *
 *   npm run wp:playground [-- --port 9400 --php 8.3 --wp latest --no-login --env staging --fixture tests/playground/seo-features.php]
 *
 * --env declares WP_ENVIRONMENT_TYPE inside the Playground (production when omitted),
 * which is how the end-to-end suite verifies the staging indexability policy.
 * --fixture installs a PHP file as a must-use plugin (test-only configuration).
 *
 * Requires vendor/ and public/build/ (composer install, npm run build).
 */
import { existsSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, join } from 'node:path';
import { spawn } from 'node:child_process';
import { parseArgs, readLocalEnv } from './lib.mjs';

const options = parseArgs(process.argv.slice(2));
const root = process.cwd();
const port = Number(options.port ?? 9400);
const php = String(options.php ?? '8.3');
const wp = String(options.wp ?? 'latest');

for (const [path, hint] of [
  ['vendor/autoload.php', 'composer install'],
  ['public/build/.vite/manifest.json', 'npm run build'],
]) {
  if (!existsSync(join(root, path))) {
    console.error(`${path} is missing: run "${hint}" first.`);
    process.exit(1);
  }
}

const folder = basename(root);
const env = readLocalEnv(root);
const environmentType = options.env ? String(options.env) : null;
const loginSlugPlugin = join(root, 'site/mu-plugins/login-slug.php');
const steps = [
  { step: 'activateTheme', themeFolderName: folder },
  { step: 'runPHP', code: readFileSync(join(root, 'tests/playground/seed.php'), 'utf8') },
];

// Same login address as a local site: the mu-plugin plus its wp-config constant,
// installed last so the setup steps above are not treated as visitor requests.
const consts = {};

if (environmentType) consts.WP_ENVIRONMENT_TYPE = environmentType;

if (options.fixture) {
  const fixture = join(root, String(options.fixture));

  if (!existsSync(fixture)) {
    console.error(`Fixture not found: ${fixture}`);
    process.exit(1);
  }

  steps.push({
    step: 'writeFile',
    path: `/wordpress/wp-content/mu-plugins/${basename(fixture)}`,
    data: readFileSync(fixture, 'utf8'),
  });
}

if (existsSync(loginSlugPlugin)) {
  steps.push({
    step: 'writeFile',
    path: '/wordpress/wp-content/mu-plugins/login-slug.php',
    data: readFileSync(loginSlugPlugin, 'utf8'),
  });
  consts.WP_LOGIN_SLUG = env.WP_LOGIN_SLUG || '_admin';
}

if (Object.keys(consts).length > 0) steps.push({ step: 'defineWpConfigConsts', consts });

const blueprint = {
  $schema: 'https://playground.wordpress.net/blueprint-schema.json',
  landingPage: '/',
  steps,
};
const blueprintPath = join(mkdtempSync(join(tmpdir(), 'playground-')), 'blueprint.json');
writeFileSync(blueprintPath, JSON.stringify(blueprint));

const args = [
  '--yes',
  '@wp-playground/cli@3',
  'server',
  `--port=${port}`,
  `--php=${php}`,
  `--wp=${wp}`,
  `--mount=${root}:/wordpress/wp-content/themes/${folder}`,
  `--blueprint=${blueprintPath}`,
];

if (options.login !== false) args.push('--login');
if (options.quiet) args.push('--verbosity=quiet');

const command = process.platform === 'win32' ? 'npx.cmd' : 'npx';
const child = spawn(command, args, { stdio: 'inherit' });

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => child.kill(signal));
}

child.on('exit', (code) => process.exit(code ?? 0));
