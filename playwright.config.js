import { defineConfig, devices } from '@playwright/test';
import { readLocalEnv } from './scripts/lib.mjs';

/**
 * End-to-end tests. BASE_URL points them at any running site (for example the
 * LocalWP site); without it a disposable WordPress Playground is started.
 */
const external = Boolean(process.env.BASE_URL);
const baseURL = process.env.BASE_URL ?? 'http://127.0.0.1:9400';
// A second Playground declares WP_ENVIRONMENT_TYPE=staging for the indexability checks.
const stagingURL = process.env.BASE_URL ?? 'http://127.0.0.1:9401';
// Against an external site, SEO_ENVIRONMENT (production | staging) says which policy to expect;
// without it the SEO specs are skipped there.
const seoEnvironment = external ? (process.env.SEO_ENVIRONMENT ?? '') : 'playground';

process.env.WP_LOGIN_SLUG ??= readLocalEnv(process.cwd()).WP_LOGIN_SLUG || '_admin';

const playground = (extraArgs, url) => ({
  command: `node scripts/playground.mjs --quiet --no-login ${extraArgs}`.trim(),
  url,
  reuseExistingServer: !process.env.CI,
  timeout: 240_000,
});

export default defineConfig({
  testDir: 'tests/e2e',
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['list']] : 'list',
  use: {
    baseURL,
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] }, testIgnore: /seo-staging/ },
    { name: 'mobile', use: { ...devices['Pixel 7'] }, testIgnore: /seo|semantics/ },
    ...(seoEnvironment === 'playground' || seoEnvironment === 'staging'
      ? [
          {
            name: 'staging',
            testMatch: /seo-staging/,
            use: { ...devices['Desktop Chrome'], baseURL: stagingURL },
          },
        ]
      : []),
  ],
  webServer: external
    ? undefined
    : [
        playground('', baseURL),
        playground(
          '--port 9401 --env staging --fixture tests/playground/seo-features.php',
          stagingURL,
        ),
      ],
});
