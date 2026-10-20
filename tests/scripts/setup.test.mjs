import assert from 'node:assert/strict';
import { test } from 'node:test';
import { renameNamespace } from '../../scripts/lib.mjs';

/** Tests for the namespace rename that the initializer applies to every PHP file. */
const rename = (content) => renameNamespace(content, 'Acme', 'ClientSite');

test('namespace declarations, imports and qualified names are renamed', () => {
  assert.equal(
    rename('namespace Acme\\Seo;\nuse Acme\\Core\\Theme;\nAcme\\Core\\Theme::boot();'),
    'namespace ClientSite\\Seo;\nuse ClientSite\\Core\\Theme;\nClientSite\\Core\\Theme::boot();',
  );
});

test('fully qualified names keep their leading backslash', () => {
  assert.equal(
    rename('$this->seo->ownership()->themeOwns(\\Acme\\Seo\\Signal::Breadcrumbs)'),
    '$this->seo->ownership()->themeOwns(\\ClientSite\\Seo\\Signal::Breadcrumbs)',
  );
  assert.equal(rename('/** @var \\Acme\\Seo\\Seo */'), '/** @var \\ClientSite\\Seo\\Seo */');
});

test('escaped names in strings are renamed', () => {
  assert.equal(
    rename("class_exists('Acme\\\\Seo\\\\Seo') || class_exists('\\\\Acme\\\\Seo\\\\Seo')"),
    "class_exists('ClientSite\\\\Seo\\\\Seo') || class_exists('\\\\ClientSite\\\\Seo\\\\Seo')",
  );
});

test('segments of other namespaces and longer identifiers are left alone', () => {
  const untouched = [
    'use Vendor\\Acme\\Thing;',
    '\\Vendor\\Acme\\Thing::run();',
    "'Vendor\\\\Acme\\\\Thing'",
    'AcmeTools\\Thing::run();',
    '$acme = new Acme();',
  ];

  for (const content of untouched) {
    assert.equal(rename(content), content);
  }
});
