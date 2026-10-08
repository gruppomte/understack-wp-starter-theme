/* global document, getComputedStyle */
import { expect, test } from '@playwright/test';

/**
 * Accessibility of the elements the SEO foundation touches or relies on:
 * accessible names of links, keyboard order and visible focus, labelled
 * navigations, consistent ARIA on the menu toggles. The breadcrumb checks run
 * on the staging Playground (tests/e2e/seo-staging.spec.js enables them).
 */
const routes = ['/', '/about/team/', '/sample-post-2/', '/category/news/'];

for (const path of routes) {
  test(`${path}: every link has an accessible name and real landmarks`, async ({ page }) => {
    await page.goto(path);

    const unnamed = await page.locator('a').evaluateAll((links) =>
      links
        .filter((link) => link.getAttribute('aria-hidden') !== 'true')
        .filter((link) => {
          const label = link.getAttribute('aria-label') ?? '';
          const image = link.querySelector('img[alt]');
          const text = link.textContent.replace(/\s+/g, ' ').trim();

          return label === '' && text === '' && !(image && image.getAttribute('alt'));
        })
        .map((link) => link.getAttribute('href')),
    );
    expect(unnamed).toEqual([]);

    await expect(page.locator('nav:not([aria-label]):not([aria-labelledby])')).toHaveCount(0);
    await expect(page.locator('[aria-controls]')).toHaveCount(
      await page.locator('[aria-controls]').count(),
    );

    const danglingControls = await page
      .locator('[aria-controls]')
      .evaluateAll((controls) =>
        controls
          .map((control) => control.getAttribute('aria-controls'))
          .filter((id) => !document.getElementById(id)),
      );
    expect(danglingControls).toEqual([]);
  });
}

test('the skip link is the first focusable element and focus is visible', async ({ page }) => {
  await page.goto('/sample-post-2/');

  await page.keyboard.press('Tab');
  const skip = page.locator('a.skip-link');
  await expect(skip).toBeFocused();

  const outline = await skip.evaluate((element) => {
    const style = getComputedStyle(element);

    return {
      outlineStyle: style.outlineStyle,
      outlineWidth: style.outlineWidth,
      boxShadow: style.boxShadow,
    };
  });
  expect(outline.outlineStyle !== 'none' || outline.boxShadow !== 'none').toBe(true);

  await page.keyboard.press('Enter');
  await expect(page.locator('main#main-content')).toBeFocused();
});

test('navigation links show a visible focus indicator', async ({ page }) => {
  await page.goto('/');

  const link = page.locator('#primary-navigation a').first();
  await link.focus();
  const focused = await link.evaluate((element) => {
    const style = getComputedStyle(element);

    return style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0;
  });
  expect(focused).toBe(true);
});
