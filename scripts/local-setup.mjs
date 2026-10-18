#!/usr/bin/env node
/**
 * Configures the local WordPress installation that hosts this theme:
 *  - installs the login-slug must-use plugin (site/mu-plugins) and its
 *    WP_LOGIN_SLUG constant, so the login screen lives at /<slug>/;
 *  - gives the development Administrator the local email address and, on the
 *    first run only, the local password.
 *
 *   npm run local:setup [-- --reset-password]
 *
 * Values come from .env.local (see .env.example). Runs only on sites whose
 * environment type is "local" or "development" and needs WP-CLI on the PATH.
 * The existing Administrator is reused and never renamed; no user is created.
 */
import { cpSync, existsSync, mkdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { commandExists, parseArgs, readIdentity, readLocalEnv, run } from './lib.mjs';

const options = parseArgs(process.argv.slice(2));
const root = process.cwd();
const env = readLocalEnv(root);
const identity = readIdentity(root);

function fail(message) {
  console.error(message);
  process.exit(1);
}

if (!existsSync(join(root, 'style.css'))) fail('Run this command from the theme directory.');
if (!env.WP_ADMIN_EMAIL)
  fail('WP_ADMIN_EMAIL is not set: copy .env.example to .env.local and fill it in.');
if (!commandExists('wp')) fail('WP-CLI ("wp") is required on the PATH.');

function findWordPress() {
  let directory = root;

  for (let depth = 0; depth < 6; depth += 1) {
    if (existsSync(join(directory, 'wp-load.php'))) return directory;
    directory = dirname(directory);
  }

  return null;
}

const wordpress = findWordPress();

if (!wordpress) fail('No WordPress installation found above the theme directory.');

/** Runs WP-CLI; PHP notices go to stderr so JSON output stays parseable. */
function wp(args, extraEnv = {}) {
  const result = run('wp', [`--path=${wordpress}`, ...args], {
    env: { ...process.env, WP_CLI_PHP_ARGS: '-d display_errors=stderr', ...extraEnv },
  });

  return { ...result, stdout: result.stdout.trim() };
}

function wpJson(args) {
  const result = wp([...args, '--format=json']);
  const start = result.stdout.search(/[[{]/);

  return result.status === 0 && start >= 0 ? JSON.parse(result.stdout.slice(start)) : null;
}

const environment = wp(['eval', 'echo wp_get_environment_type();']).stdout;

if (!['local', 'development'].includes(environment)) {
  fail(
    `This site reports the "${environment || 'unknown'}" environment: local setup runs only on local or development sites.`,
  );
}

console.log(`WordPress: ${wordpress} (${environment})`);

// 1. Login slug: must-use plugin and constant.
const slug = env.WP_LOGIN_SLUG || '_admin';
const pluginSource = join(root, 'site/mu-plugins/login-slug.php');
const pluginTarget = join(wordpress, 'wp-content/mu-plugins/login-slug.php');

if (
  !existsSync(pluginTarget) ||
  readFileSync(pluginTarget, 'utf8') !== readFileSync(pluginSource, 'utf8')
) {
  mkdirSync(dirname(pluginTarget), { recursive: true });
  cpSync(pluginSource, pluginTarget);
  console.log('Login slug plugin installed in wp-content/mu-plugins.');
} else {
  console.log('Login slug plugin up to date.');
}

const configured = ['WP_LOGIN_SLUG', 'LOGIN_SLUG']
  .map((name) => ({ name, value: wp(['config', 'get', name, '--type=constant']) }))
  .find((item) => item.value.status === 0);

if (configured) {
  console.log(`Login address: /${configured.value.stdout}/ (${configured.name} in wp-config.php)`);
} else if (wp(['config', 'set', 'WP_LOGIN_SLUG', slug, '--type=constant']).status === 0) {
  console.log(`Login address: /${slug}/ (WP_LOGIN_SLUG added to wp-config.php)`);
} else {
  fail('Could not write WP_LOGIN_SLUG to wp-config.php; add it by hand.');
}

if (wp(['option', 'get', 'permalink_structure']).stdout === '') {
  console.warn('Pretty permalinks are off: the login slug needs them (Settings > Permalinks).');
}

// 2. Development administrator: reuse the existing account, never create or rename one.
const administrators =
  wpJson([
    'user',
    'list',
    '--role=administrator',
    '--fields=ID,user_login,user_email',
    '--orderby=ID',
    '--order=ASC',
  ]) ?? [];

if (administrators.length === 0)
  fail('No Administrator found; create the site administrator first.');

const admin =
  administrators.find((user) => user.user_email === env.WP_ADMIN_EMAIL) ?? administrators[0];

if (admin.user_email !== env.WP_ADMIN_EMAIL) {
  wp(['user', 'update', String(admin.ID), `--user_email=${env.WP_ADMIN_EMAIL}`, '--skip-email']);
  console.log(`Administrator "${admin.user_login}" email set to ${env.WP_ADMIN_EMAIL}.`);
} else {
  console.log(`Administrator "${admin.user_login}" already uses ${env.WP_ADMIN_EMAIL}.`);
}

if (wp(['option', 'get', 'admin_email']).stdout !== env.WP_ADMIN_EMAIL) {
  wp(['option', 'update', 'admin_email', env.WP_ADMIN_EMAIL]);
  console.log('Administration email address updated.');
}

// 3. Local password: first run only (or --reset-password), passed through the environment.
const marker = `${identity.slug.replace(/-/g, '_')}_local_setup`;
const firstRun = wp(['option', 'get', marker]).status !== 0;

if (firstRun || options['reset-password']) {
  if (!env.WP_ADMIN_PASSWORD) {
    console.warn('WP_ADMIN_PASSWORD is empty in .env.local: password left unchanged.');
  } else {
    const result = wp(
      [
        'eval',
        'wp_set_password((string) getenv("WP_ADMIN_PASSWORD"), (int) getenv("WP_ADMIN_ID"));',
      ],
      { WP_ADMIN_PASSWORD: env.WP_ADMIN_PASSWORD, WP_ADMIN_ID: String(admin.ID) },
    );

    if (result.status !== 0) fail(`Could not set the password:\n${result.stderr}`);

    wp(['option', 'update', marker, new Date().toISOString(), '--autoload=no']);
    console.log(`Local password applied to "${admin.user_login}".`);
  }
} else {
  console.log('Password unchanged (pass --reset-password to apply the local password again).');
}

const home = wp(['option', 'get', 'home']).stdout;
console.log(
  `\nLogin: ${home}/${configured ? configured.value.stdout : slug}/ as "${admin.user_login}".`,
);
