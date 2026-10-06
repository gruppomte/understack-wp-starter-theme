import { expect, test } from '@playwright/test';
import { watchErrors } from './helpers.js';

test.describe('primary navigation', () => {
  test('mobile menu opens, closes with Escape and returns focus', async ({ page, isMobile }) => {
    test.skip(!isMobile, 'mobile layout only');

    const errors = watchErrors(page);
    await page.goto('/');

    const toggle = page.locator('[data-nav-toggle]');
    const panel = page.locator('#primary-navigation');

    await expect(toggle).toBeVisible();
    await expect(panel).toBeHidden();

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(panel).toBeVisible();

    await panel.locator('a').first().focus();
    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(panel).toBeHidden();
    await expect(toggle).toBeFocused();
    expect(errors()).toEqual([]);
  });

  test('sub-menus work with the keyboard', async ({ page, isMobile }) => {
    test.skip(isMobile, 'desktop layout only');

    await page.goto('/');

    const toggle = page.locator('[data-submenu-toggle]').first();
    test.skip((await toggle.count()) === 0, 'no sub-menu in the primary menu');

    const panel = page.locator(`#${await toggle.getAttribute('aria-controls')}`);

    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(panel).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(panel).toBeHidden();
  });
});
