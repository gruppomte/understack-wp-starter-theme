#!/usr/bin/env node
/**
 * Copies an authorized ACF PRO package into includes/acf so the theme can load it.
 *
 *   npm run acf:install -- /path/to/advanced-custom-fields-pro.zip
 *   npm run acf:install -- /path/to/advanced-custom-fields-pro/
 *
 * Without an argument, acf-pro.zip or advanced-custom-fields-pro.zip in the theme
 * root is used. No license information is read or stored.
 */
import {
  cpSync,
  existsSync,
  mkdtempSync,
  readFileSync,
  readdirSync,
  renameSync,
  rmSync,
  statSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { commandExists, run } from './lib.mjs';

const root = process.cwd();
const target = join(root, 'includes', 'acf');
const candidates = ['acf-pro.zip', 'advanced-custom-fields-pro.zip'].map((name) =>
  join(root, name),
);
const requested = process.argv[2] ?? candidates.find((candidate) => existsSync(candidate));
const source = requested ? resolve(requested) : null;

if (!source || !existsSync(source)) {
  console.error(
    'Provide the path to the ACF PRO zip or folder, or place acf-pro.zip in the theme root.',
  );
  process.exit(1);
}

if (source === target || source.startsWith(`${target}/`) || source === root) {
  console.error('The source must be a package outside includes/acf.');
  process.exit(1);
}

function findPluginRoot(directory) {
  if (existsSync(join(directory, 'acf.php'))) {
    return directory;
  }

  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    if (entry.isDirectory() && entry.name !== '__MACOSX') {
      const found = findPluginRoot(join(directory, entry.name));

      if (found) {
        return found;
      }
    }
  }

  return null;
}

function validate(pluginRoot) {
  const header = readFileSync(join(pluginRoot, 'acf.php'), 'utf8').slice(0, 4000);
  const name = header.match(/Plugin Name:\s*(.+)/)?.[1]?.trim();
  const version = header.match(/Version:\s*(.+)/)?.[1]?.trim();

  if (!name || !/Advanced Custom Fields PRO/i.test(name) || !existsSync(join(pluginRoot, 'pro'))) {
    throw new Error(`"${pluginRoot}" does not look like the Advanced Custom Fields PRO plugin.`);
  }

  return { name, version };
}

let workDirectory = null;
let pluginRoot;

try {
  if (statSync(source).isDirectory()) {
    pluginRoot = findPluginRoot(source);
  } else {
    workDirectory = mkdtempSync(join(tmpdir(), 'acf-'));
    const extracted = commandExists('unzip')
      ? run('unzip', ['-q', source, '-d', workDirectory])
      : run('tar', ['-xf', source, '-C', workDirectory]);

    if (extracted.status !== 0) {
      throw new Error(
        `Could not extract "${source}": ${extracted.stderr || 'unzip or tar is required.'}`,
      );
    }

    pluginRoot = findPluginRoot(workDirectory);
  }

  if (!pluginRoot) {
    throw new Error('acf.php was not found in the provided package.');
  }

  const plugin = validate(pluginRoot);

  // Copy next to the target first so a failed copy never leaves the theme without ACF.
  const staged = `${target}.new`;
  rmSync(staged, { recursive: true, force: true });
  cpSync(pluginRoot, staged, {
    recursive: true,
    filter: (path) => !/(^|\/)(\.DS_Store|__MACOSX)$/.test(path),
  });
  rmSync(target, { recursive: true, force: true });
  renameSync(staged, target);

  console.log(`${plugin.name} ${plugin.version} installed in includes/acf.`);
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
} finally {
  if (workDirectory) {
    rmSync(workDirectory, { recursive: true, force: true });
  }
}
