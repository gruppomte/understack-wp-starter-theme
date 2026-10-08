import assert from 'node:assert/strict';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { test } from 'node:test';
import { scan } from '../../scripts/scan.mjs';

/**
 * Tests for the residue scan. The forbidden term used below is decoded at
 * runtime so that this file never matches the scan itself.
 */
const term = Buffer.from('Y2hhdGdwdA==', 'base64').toString('utf8');

const identity = {
  slug: 'client-site',
  name: 'Client Site',
  version: '1.0.0',
  textDomain: 'client-site',
  namespace: 'ClientSite',
  composerName: 'client-site/theme',
  directory: 'client-site',
};

function withFixture(files, callback) {
  const root = mkdtempSync(join(tmpdir(), 'residue-scan-'));

  try {
    for (const [path, content] of Object.entries(files)) {
      mkdirSync(dirname(join(root, path)), { recursive: true });
      writeFileSync(join(root, path), content);
    }

    return callback(root);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
}

function violatingFiles(root) {
  return scan(root, { identity }).violations.map((match) => match.file.split('\\').join('/'));
}

test('files that ship are scanned', () => {
  withFixture({ 'app/Thing.php': `<?php // ${term}` }, (root) => {
    assert.deepEqual(violatingFiles(root), ['app/Thing.php']);
  });
});

test('documentation is development-only and never scanned', () => {
  withFixture(
    {
      'docs/guide.md': `Names ${term} on purpose.`,
      'docs/nested/notes.md': `Names ${term} again.`,
      'app/Thing.php': `<?php // ${term}`,
    },
    (root) => {
      assert.deepEqual(violatingFiles(root), ['app/Thing.php']);
    },
  );
});

test('only the top-level docs directory is exempt', () => {
  withFixture({ 'views/docs/page.twig': `{# ${term} #}` }, (root) => {
    assert.deepEqual(violatingFiles(root), ['views/docs/page.twig']);
  });
});

test('local environment files stay out of the scan', () => {
  withFixture({ '.env.local': `TOOL=${term}`, '.env.example': `TOOL=${term}` }, (root) => {
    assert.deepEqual(violatingFiles(root), []);
  });
});

test('a clean tree reports no violations', () => {
  withFixture({ 'app/Thing.php': '<?php // nothing to see' }, (root) => {
    assert.deepEqual(violatingFiles(root), []);
  });
});
