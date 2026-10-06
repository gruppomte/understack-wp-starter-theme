import { expect, test } from '@playwright/test';
import { watchErrors } from './helpers.js';

test('homepage renders without errors', async ({ page }) => {
  const errors = watchErrors(page);
  const response = await page.goto('/');

  expect(response?.status()).toBe(200);
  await expect(page.locator('main#main-content')).toBeVisible();
  await expect(page.locator('h1')).toBeVisible();
  await expect(page.locator('a.skip-link')).toHaveAttribute('href', '#main-content');
  expect(errors()).toEqual([]);
});

test('critical navigation reaches a page', async ({ page }) => {
  const errors = watchErrors(page);
  await page.goto('/');
  const home = page.url();

  const toggle = page.locator('[data-nav-toggle]');

  if (await toggle.isVisible()) {
    await toggle.click();
  }

  const link = page.locator('#primary-navigation a').first();
  const label = (await link.textContent())?.trim();

  await link.click();
  await expect(page).not.toHaveURL(home);
  await expect(page.locator('main h1')).toContainText(label ?? '');
  expect(errors()).toEqual([]);
});

test('unknown URLs return the 404 template', async ({ page }) => {
  const response = await page.goto(`/no-such-page-${Date.now()}/`);

  expect(response?.status()).toBe(404);
  await expect(page.locator('main#main-content h1')).toBeVisible();
  await expect(page.locator('main#main-content form[role="search"]')).toBeVisible();
});
