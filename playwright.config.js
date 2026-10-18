import { defineConfig, devices } from '@playwright/test';
import { readLocalEnv } from './scripts/lib.mjs';

/**
 * End-to-end tests. BASE_URL points them at any running site (for example the
 * LocalWP site); without it disposable WordPress Playgrounds are started on
 * their own ports, away from "npm run wp:playground" (9400).
 */
const external = Boolean(process.env.BASE_URL);
const baseURL = process.env.BASE_URL ?? 'http://127.0.0.1:9410';
// A second Playground declares WP_ENVIRONMENT_TYPE=staging for the indexability checks.
const stagingURL = process.env.BASE_URL ?? 'http://127.0.0.1:9411';
// Against an external site, SEO_ENVIRONMENT (production | staging) says which policy to expect;
// without it the SEO specs are skipped there.
const seoEnvironment = external ? (process.env.SEO_ENVIRONMENT ?? '') : 'playground';

process.env.WP_LOGIN_SLUG ??= readLocalEnv(process.cwd()).WP_LOGIN_SLUG || '_admin';

// Every run starts fresh Playgrounds: a server already on the port may hold another theme,
// older code or state left by earlier runs, so Playwright stops instead of reusing it.
// E2E_REUSE_SERVER=1 reuses one on purpose, for quick iterations on a known server.
const playground = (extraArgs, url) => ({
  command:
    `node scripts/playground.mjs --quiet --no-login --port ${new URL(url).port} ${extraArgs}`.trim(),
  url,
  reuseExistingServer: process.env.E2E_REUSE_SERVER === '1',
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
    {
      name: 'desktop',
      use: { ...devices['Desktop Chrome'] },
      testIgnore: /seo-staging|matrix-staging/,
    },
    { name: 'mobile', use: { ...devices['Pixel 7'] }, testIgnore: /seo|semantics|a11y|matrix/ },
    ...(seoEnvironment === 'playground' || seoEnvironment === 'staging'
      ? [
          {
            name: 'staging',
            testMatch: /seo-staging|matrix-staging/,
            use: { ...devices['Desktop Chrome'], baseURL: stagingURL },
          },
        ]
      : []),
  ],
  webServer: external
    ? undefined
    : [
        playground('', baseURL),
        playground('--env staging --fixture tests/playground/seo-features.php', stagingURL),
      ],
});
