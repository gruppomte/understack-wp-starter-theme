#!/usr/bin/env node
/**
 * Project initializer: turns this theme into an independent client theme.
 *
 *   npm run setup                                   interactive
 *   npm run setup -- --name "Client Name" --yes     non-interactive with defaults
 *
 * Options: --slug, --text-domain, --namespace, --woocommerce yes|no,
 *          --gsap yes|no, --alpine yes|no, --acf yes|no,
 *          --no-install, --no-build, --dry-run
 *
 * Re-running on an initialized theme renames it again. Third-party code
 * (vendor, node_modules, bundled plugins) is never modified.
 */
import { existsSync, readFileSync, readdirSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { createInterface } from 'node:readline/promises';
import { stdin, stdout } from 'node:process';
import {
  commandExists,
  escapeRegExp,
  parseArgs,
  readIdentity,
  run,
  toBoolean,
  walk,
} from './lib.mjs';
import { formatReport, scan } from './scan.mjs';
import { generatePot } from './i18n.mjs';

const PHP_RESERVED = new Set(
  'abstract and array as break callable case catch class clone const continue declare default do echo else elseif empty enddeclare endfor endforeach endif endswitch endwhile enum eval exit extends final finally fn for foreach function global goto if implements include instanceof insteadof interface isset list match namespace new or print private protected public readonly require return static switch throw trait try unset use var while xor yield int float bool string true false null void never mixed iterable object'.split(
    ' ',
  ),
);

const options = parseArgs(process.argv.slice(2));
const dryRun = Boolean(options['dry-run']);
const interactive = !options.yes && stdin.isTTY;
let root = process.cwd();

if (!existsSync(join(root, 'style.css')) || !existsSync(join(root, 'package.json'))) {
  fail('Run this command from the theme directory.');
}

const current = readIdentity(root);
const log = (message) => console.log(`${dryRun ? '[dry-run] ' : ''}${message}`);
const changes = [];

const io = {
  write(path, content) {
    changes.push(`update ${relative(root, path)}`);
    if (!dryRun) writeFileSync(path, content);
  },
  remove(path) {
    if (!existsSync(path)) return;
    changes.push(`remove ${relative(root, path)}`);
    if (!dryRun) rmSync(path, { recursive: true, force: true });
  },
  rename(from, to) {
    changes.push(`rename ${from} -> ${to}`);
    if (!dryRun) renameSync(from, to);
  },
};

function fail(message) {
  console.error(message);
  process.exit(1);
}

function sanitizeSlug(value) {
  return value
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function toNamespace(value) {
  const words = value
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^A-Za-z0-9]+/g, ' ')
    .trim()
    .split(/\s+/)
    .filter(Boolean);
  let namespace = words.map((word) => word[0].toUpperCase() + word.slice(1)).join('');

  if (/^[0-9]/.test(namespace)) {
    namespace = `Project${namespace}`;
  }

  return namespace;
}

function validate(next) {
  const errors = [];

  if (!next.name || next.name.length > 100 || /[<>"\p{Cc}]/u.test(next.name)) {
    errors.push('Project name is required (max 100 characters, no HTML).');
  }

  for (const [label, value] of [
    ['Project slug', next.slug],
    ['Text domain', next.textDomain],
  ]) {
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value) || value.length > 60) {
      errors.push(`${label} must contain only lowercase letters, digits and single hyphens.`);
    }
  }

  if (
    !/^[A-Z][A-Za-z0-9]*$/.test(next.namespace) ||
    PHP_RESERVED.has(next.namespace.toLowerCase())
  ) {
    errors.push(
      'PHP namespace must be PascalCase, start with a letter and not be a reserved word.',
    );
  }

  return errors;
}

async function collectAnswers() {
  const rl = interactive ? createInterface({ input: stdin, output: stdout }) : null;
  const ask = async (question, fallback) => {
    if (!rl) return fallback;
    const answer = (await rl.question(`${question} [${fallback}]: `)).trim();
    return answer === '' ? fallback : answer;
  };
  const askBoolean = async (question, fallback) =>
    toBoolean(await ask(`${question} (yes/no)`, fallback ? 'yes' : 'no'), fallback);

  const name = await ask('Project name', options.name ?? current.name);
  const slug = sanitizeSlug(await ask('Project slug', options.slug ?? sanitizeSlug(name)));
  const textDomain = sanitizeSlug(await ask('Text domain', options['text-domain'] ?? slug));
  const namespace = await ask('PHP namespace', options.namespace ?? toNamespace(name));
  const features = {
    woocommerce: await askBoolean('WooCommerce', toBoolean(options.woocommerce, false)),
    gsap: await askBoolean('GSAP', toBoolean(options.gsap, true)),
    alpine: await askBoolean('Alpine.js', toBoolean(options.alpine, true)),
    acf: await askBoolean('ACF Pro', toBoolean(options.acf, true)),
  };
  const install = await askBoolean('Install dependencies', toBoolean(options.install, true));
  const build = await askBoolean('Run the production build', toBoolean(options.build, true));

  rl?.close();

  return { name: name.trim(), slug, textDomain, namespace, features, install, build };
}

function editJsonFile(rel, indent, transform) {
  editFile(rel, (content) => {
    const data = JSON.parse(content);
    transform(data);
    return `${JSON.stringify(data, null, indent)}\n`;
  });
}

function renameKey(object, from, to) {
  if (!object || !(from in object) || from === to) return;
  const entries = Object.entries(object).map(([key, value]) => [key === from ? to : key, value]);
  for (const key of Object.keys(object)) delete object[key];
  Object.assign(object, Object.fromEntries(entries));
}

/**
 * Identifiers are replaced only where they are defined or used as identifiers
 * (package names, theme header, constants, namespace, text domain arguments),
 * never as free text: a slug such as "client" or "build" must not touch paths
 * like "@vite/client" or "public/build" when the theme is renamed again later.
 */
function replaceIdentifiers(next) {
  const textDomain = escapeRegExp(current.textDomain);
  const domainPattern = new RegExp(`(['"])${textDomain}\\1(\\s*\\))`, 'g');
  const namespacePattern = new RegExp(
    `(?<![\\w\\\\])${escapeRegExp(current.namespace)}(?=\\\\)`,
    'g',
  );
  const slugConstant = new RegExp(`(const SLUG = ')${escapeRegExp(current.slug)}(')`);
  const domainConstant = new RegExp(`(const TEXT_DOMAIN = ')${textDomain}(')`);

  editJsonFile('package.json', 2, (pkg) => {
    pkg.name = next.slug;
  });
  editJsonFile('package-lock.json', 2, (lock) => {
    lock.name = next.slug;
    if (lock.packages?.['']) lock.packages[''].name = next.slug;
  });
  editJsonFile('composer.json', 4, (composer) => {
    if (typeof composer.name === 'string' && composer.name.startsWith(`${current.slug}/`)) {
      composer.name = `${next.slug}${composer.name.slice(current.slug.length)}`;
    }
    for (const section of ['autoload', 'autoload-dev']) {
      renameKey(composer[section]?.['psr-4'], `${current.namespace}\\`, `${next.namespace}\\`);
    }
  });
  editFile('style.css', (content) =>
    content
      .replace(/^(\s*Theme Name:\s*).*$/m, (_, prefix) => `${prefix}${next.name}`)
      .replace(
        new RegExp(`^(\\s*Text Domain:\\s*)${textDomain}\\s*$`, 'm'),
        (_, prefix) => `${prefix}${next.textDomain}`,
      ),
  );
  editFile('phpcs.xml.dist', (content) =>
    content.replace(
      new RegExp(`(name="text_domain"[\\s\\S]*?value=")${textDomain}(")`),
      (_, prefix, suffix) => `${prefix}${next.textDomain}${suffix}`,
    ),
  );

  let updated = 0;

  for (const file of walk(root)) {
    if (!/\.(php|twig)$/.test(file)) continue;

    const original = readFileSync(file, 'utf8');
    let content = original.replace(
      domainPattern,
      (_, quote, suffix) => `${quote}${next.textDomain}${quote}${suffix}`,
    );

    if (file.endsWith('.php')) {
      content = content
        .replace(namespacePattern, next.namespace)
        .replace(slugConstant, (_, prefix, suffix) => `${prefix}${next.slug}${suffix}`)
        .replace(domainConstant, (_, prefix, suffix) => `${prefix}${next.textDomain}${suffix}`);
    }

    if (content !== original) {
      io.write(file, content);
      updated += 1;
    }
  }

  log(`Identifiers updated in ${updated} PHP/Twig file(s).`);
}

function editFile(rel, transform) {
  const path = join(root, rel);
  if (!existsSync(path)) return;
  const original = readFileSync(path, 'utf8');
  const content = transform(original);
  if (content !== original) io.write(path, content);
}

function removeLines(rel, pattern) {
  editFile(rel, (content) =>
    content
      .split('\n')
      .filter((line) => !pattern.test(line))
      .join('\n'),
  );
}

function editPackageJson(transform) {
  editFile('package.json', (content) => {
    const pkg = JSON.parse(content);
    transform(pkg);
    return `${JSON.stringify(pkg, null, 2)}\n`;
  });
}

function removeEmptyDirectory(rel) {
  const path = join(root, rel);
  if (existsSync(path) && readdirSync(path).length === 0) io.remove(path);
}

function applyFeatures(features) {
  if (!features.woocommerce) {
    for (const path of [
      'app/Integrations/WooCommerce.php',
      'app/Controllers/WooCommerceController.php',
      'woocommerce.php',
      'views/woocommerce',
      'src/css/woocommerce.css',
    ]) {
      io.remove(join(root, path));
    }
    removeLines('app/Core/Theme.php', /WooCommerce/);
    removeLines('phpcs.xml.dist', /woocommerce\.php/);
    removeLines('vite.config.js', /woocommerce: 'src\/css\/woocommerce\.css'/);
    removeEmptyDirectory('app/Integrations');
  }

  if (!features.gsap) {
    io.remove(join(root, 'src/js/animations'));
    editFile('src/js/app.js', (content) =>
      content.replace(/\n\s*\{[^{}]*animations\/index\.js[^{}]*\},?/, ''),
    );
    editPackageJson((pkg) => delete pkg.dependencies?.gsap);
    editFile('src/css/base.css', (content) =>
      content.replace(
        /\n\s*\/\*\n\s*\* Elements revealed by the animation module[\s\S]*?opacity: 0;\n\s*\}\n/,
        '\n',
      ),
    );
    editFile('app/Core/Assets.php', (content) =>
      content.replace(
        /\n\s*\.\s*'if\(!window\.matchMedia[^\n]*\n\s*\.\s*'\{document\.documentElement\.classList\.add\("motion"\)\}'/,
        '',
      ),
    );
    for (const file of walk(root, join(root, 'views'))) {
      editFile(relative(root, file), (content) =>
        content.replace(
          / data-(?:hero-item|hero|animate-stagger|animate|parallax)(?:="[^"]*")?/g,
          '',
        ),
      );
    }
  }

  if (!features.alpine) {
    io.remove(join(root, 'src/js/modules/alpine.js'));
    editFile('src/js/app.js', (content) =>
      content.replace(/import '\.\/modules\/alpine\.js';\n\n?/, ''),
    );
    editPackageJson((pkg) => delete pkg.dependencies?.alpinejs);
    editFile('src/css/base.css', (content) =>
      content.replace(/\n\s*\/\* Alpine hides[^\n]*\n\s*\[x-cloak\] \{[^}]*\}\n/, '\n'),
    );

    const dependents = walk(root, join(root, 'views')).filter((file) =>
      /\sx-data=|\sx-show=|\s@click|\s:class=|\s:aria-/.test(readFileSync(file, 'utf8')),
    );

    if (dependents.length > 0) {
      console.warn('Templates using Alpine directives need a replacement:');
      for (const file of dependents) console.warn(`  ${relative(root, file)}`);
    }
  }

  if (!features.acf) {
    for (const path of ['includes/acf', 'acf-json', 'app/ACF']) io.remove(join(root, path));
    removeLines('app/Core/Theme.php', /\\ACF\\|Acf\w+::class/);
    removeEmptyDirectory('includes');
  } else if (!existsSync(join(root, 'includes/acf/acf.php'))) {
    console.warn('ACF Pro is not bundled yet: run "npm run acf:install -- <path-to-acf-pro.zip>".');
  }
}

function removeStarterFiles() {
  for (const entry of readdirSync(root)) {
    if (/\.md$/i.test(entry) || entry === '.DS_Store') io.remove(join(root, entry));
  }
}

function renameDirectory(next) {
  if (next.slug === current.directory) return;

  const target = join(dirname(root), next.slug);

  if (existsSync(target)) {
    console.warn(`Directory "${target}" already exists; the theme directory was not renamed.`);
    return;
  }

  io.rename(root, target);

  if (!dryRun) {
    root = target;
    process.chdir(root);
  }
}

function refreshComposerLock(next) {
  if (next.slug === current.slug || dryRun) return;

  if (!commandExists('composer')) {
    console.warn(
      'Composer is not available: run "composer update --lock" to refresh composer.lock.',
    );
    return;
  }

  const result = run(
    'composer',
    ['update', '--lock', '--no-install', '--no-interaction', '--quiet'],
    { cwd: root },
  );

  if (result.status !== 0) {
    console.warn(`composer update --lock failed:\n${result.stderr}`);
  }
}

function installDependencies(dependenciesChanged) {
  if (dryRun) return true;

  const composer = commandExists('composer')
    ? run('composer', ['install', '--no-interaction', '--prefer-dist'], {
        cwd: root,
        inherit: true,
      })
    : { status: 1, stderr: 'composer not found' };
  const npm = run('npm', [dependenciesChanged ? 'install' : 'ci', '--no-audit', '--no-fund'], {
    cwd: root,
    inherit: true,
  });

  if (composer.status !== 0) console.warn('Composer install did not complete.');
  if (npm.status !== 0) console.warn('npm install did not complete.');

  return composer.status === 0 && npm.status === 0;
}

function build() {
  if (dryRun) return true;

  if (!existsSync(join(root, 'node_modules'))) {
    console.warn('node_modules is missing: run "npm ci" and "npm run build".');
    return false;
  }

  return run('npm', ['run', 'build'], { cwd: root, inherit: true }).status === 0;
}

const next = await collectAnswers();
const errors = validate(next);

if (errors.length > 0) {
  fail(errors.join('\n'));
}

console.log(`
Project name:   ${next.name}
Slug:           ${next.slug}
Text domain:    ${next.textDomain}
PHP namespace:  ${next.namespace}
WooCommerce:    ${next.features.woocommerce ? 'yes' : 'no'}
GSAP:           ${next.features.gsap ? 'yes' : 'no'}
Alpine.js:      ${next.features.alpine ? 'yes' : 'no'}
ACF Pro:        ${next.features.acf ? 'yes' : 'no'}
`);

if (interactive) {
  const rl = createInterface({ input: stdin, output: stdout });
  const confirmed = toBoolean(await rl.question('Continue? (yes/no) [yes]: '), true);
  rl.close();
  if (!confirmed) fail('Aborted.');
}

replaceIdentifiers(next);
applyFeatures(next.features);
removeStarterFiles();
renameDirectory(next);
refreshComposerLock(next);

if (!dryRun && existsSync(join(root, 'languages'))) {
  const pot = generatePot(root);
  changes.push(`regenerate ${pot.output}`);
}

const dependenciesChanged = !next.features.gsap || !next.features.alpine;
let installed = true;
let built = true;

if (next.install) installed = installDependencies(dependenciesChanged);
if (next.build) built = build();

const result = dryRun ? null : scan(root, { strict: true });

console.log(`
Changes: ${changes.length}
${changes
  .slice(0, 40)
  .map((change) => `  ${change}`)
  .join('\n')}${changes.length > 40 ? '\n  ...' : ''}

Theme directory: ${root}
${result ? formatReport(result) : 'Residue scan skipped (dry run).'}
`);

if (!dryRun) {
  console.log('Next steps:');
  console.log(
    `  - activate the theme in WordPress (Appearance > Themes) or run: wp theme activate ${next.slug}`,
  );
  console.log('  - start developing with "npm run dev", release with "npm run release"');
}

if ((result && result.violations.length > 0) || !installed || !built) {
  process.exit(1);
}
