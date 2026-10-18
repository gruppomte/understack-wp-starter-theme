import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { basename, extname, join, relative } from 'node:path';
import { spawnSync } from 'node:child_process';

/** Directories that are never part of the theme's own code. */
export const EXCLUDED_DIRECTORIES = new Set([
  'node_modules',
  'vendor',
  'dist',
  '.git',
  '.vite',
  '.phpstan-cache',
  'test-results',
  'playwright-report',
  'blob-report',
  '.lighthouseci',
]);

/** Third-party or generated paths (relative to the theme root) that are never scanned or rewritten. */
export const EXCLUDED_PATHS = ['includes/acf', 'public/build', 'public/hot'];

export const TEXT_EXTENSIONS = new Set([
  '.php',
  '.twig',
  '.js',
  '.mjs',
  '.cjs',
  '.css',
  '.json',
  '.xml',
  '.dist',
  '.md',
  '.txt',
  '.yml',
  '.yaml',
  '.html',
  '.svg',
  '.editorconfig',
  '.gitignore',
  '.prettierrc',
  '.prettierignore',
]);

/**
 * Current identity of the theme, derived from the files that define it so the
 * scripts never need to hardcode names.
 */
export function readIdentity(root) {
  const pkg = JSON.parse(readFileSync(join(root, 'package.json'), 'utf8'));
  const composer = JSON.parse(readFileSync(join(root, 'composer.json'), 'utf8'));
  const style = readFileSync(join(root, 'style.css'), 'utf8');
  const header = (name) => style.match(new RegExp(`^\\s*${name}:\\s*(.+?)\\s*$`, 'mi'))?.[1] ?? '';
  const namespace = Object.keys(composer.autoload?.['psr-4'] ?? {})[0]?.replace(/\\+$/, '') ?? '';

  return {
    slug: pkg.name,
    name: header('Theme Name'),
    version: header('Version'),
    textDomain: header('Text Domain') || pkg.name,
    namespace,
    composerName: composer.name ?? '',
    directory: basename(root),
  };
}

export function isExcluded(root, absolutePath) {
  const rel = relative(root, absolutePath).split('\\').join('/');

  return EXCLUDED_PATHS.some((path) => rel === path || rel.startsWith(`${path}/`));
}

/** Recursively lists the theme's own files. */
export function walk(root, directory = root, files = []) {
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name);

    if (entry.isDirectory()) {
      if (EXCLUDED_DIRECTORIES.has(entry.name) || isExcluded(root, path)) {
        continue;
      }

      walk(root, path, files);
      continue;
    }

    if (entry.isFile() && !isExcluded(root, path)) {
      files.push(path);
    }
  }

  return files;
}

export function isTextFile(path) {
  const name = basename(path);
  const extension = extname(name) || name;

  return TEXT_EXTENSIONS.has(extension) || TEXT_EXTENSIONS.has(name);
}

export function fileSize(path) {
  return statSync(path).size;
}

export function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Renames the root PHP namespace in source code: qualified names
 * (`Acme\Seo\Signal`), fully qualified names (`\Acme\Seo\Signal`) and their
 * escaped form in strings (`'\\Acme\\Seo'`). A segment of another namespace
 * (`Vendor\Acme\…`) is left alone.
 */
export function renameNamespace(content, from, to) {
  const pattern = new RegExp(`(?<![\\w\\\\])(\\\\{0,2})${escapeRegExp(from)}(?=\\\\)`, 'g');

  return content.replace(pattern, (_, prefix) => `${prefix}${to}`);
}

/** Windows ships npm, npx and composer as .cmd shims; resolving them avoids a shell. */
function executable(command) {
  if (process.platform === 'win32' && ['npm', 'npx', 'composer'].includes(command)) {
    return `${command}.cmd`;
  }

  return command;
}

/** Runs a command without a shell, returning { status, stdout, stderr }. */
export function run(command, args, options = {}) {
  const result = spawnSync(executable(command), args, {
    encoding: 'utf8',
    stdio: options.inherit ? 'inherit' : 'pipe',
    ...options,
  });

  return {
    status: result.status ?? 1,
    stdout: result.stdout ?? '',
    stderr: result.stderr ?? '',
    error: result.error,
  };
}

export function commandExists(command) {
  const probe = run(command, ['--version']);

  return !probe.error && probe.status === 0;
}

/** Minimal argv parser: --flag, --no-flag, --key value, --key=value. */
export function parseArgs(argv) {
  const options = { _: [] };

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];

    if (!arg.startsWith('--')) {
      options._.push(arg);
      continue;
    }

    const body = arg.slice(2);

    if (body.includes('=')) {
      const [key, ...rest] = body.split('=');
      options[key] = rest.join('=');
      continue;
    }

    if (body.startsWith('no-')) {
      options[body.slice(3)] = false;
      continue;
    }

    const next = argv[index + 1];

    if (next !== undefined && !next.startsWith('--')) {
      options[body] = next;
      index += 1;
    } else {
      options[body] = true;
    }
  }

  return options;
}

export function toBoolean(value, fallback) {
  if (value === undefined) {
    return fallback;
  }

  if (typeof value === 'boolean') {
    return value;
  }

  return ['1', 'y', 'yes', 'true', 'on'].includes(String(value).trim().toLowerCase());
}

/**
 * Local configuration: .env.example provides the defaults, .env.local (ignored
 * by Git) the real values. Only the Node tooling reads these files.
 */
export function readLocalEnv(root) {
  const values = {};

  for (const name of ['.env.example', '.env.local']) {
    const path = join(root, name);

    if (!existsSync(path)) continue;

    for (const line of readFileSync(path, 'utf8').split(/\r?\n/)) {
      const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);

      if (!match || line.trim().startsWith('#')) continue;

      const value = match[2].replace(/^(['"])(.*)\1$/, '$2');

      if (value !== '' || !(match[1] in values)) values[match[1]] = value;
    }
  }

  return values;
}
