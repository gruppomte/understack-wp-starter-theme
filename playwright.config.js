import { defineConfig, devices } from '@playwright/test';
import { readLocalEnv } from './scripts/lib.mjs';

/**
 * End-to-end tests. BASE_URL points them at any running site (for example the
 * LocalWP site); without it a disposable WordPress Playground is started.
 */
const baseURL = process.env.BASE_URL ?? 'http://127.0.0.1:9400';

process.env.WP_LOGIN_SLUG ??= readLocalEnv(process.cwd()).WP_LOGIN_SLUG || '_admin';

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
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
  webServer: process.env.BASE_URL
    ? undefined
    : {
        command: 'node scripts/playground.mjs --quiet --no-login',
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        timeout: 240_000,
      },
});
