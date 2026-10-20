/* global document, window, MutationObserver */
import { expect, test } from '@playwright/test';
import { watchErrors } from './helpers.js';

const LENIS = /(^|\s)lenis(\s|$)/;

test.describe('smooth scrolling', () => {
  test('Lenis drives the page on fine-pointer devices; anchors and focus keep working', async ({
    page,
    isMobile,
  }) => {
    test.skip(isMobile, 'touch devices keep native scrolling');

    const errors = watchErrors(page);
    await page.goto('/');

    await expect(page.locator('html')).toHaveClass(LENIS);
    expect(await page.evaluate(() => typeof window.lenis?.scrollTo)).toBe('function');

    // The wheel moves the page through Lenis.
    const scrollable = await page.evaluate(
      () => document.documentElement.scrollHeight - window.innerHeight > 300,
    );

    if (scrollable) {
      await page.mouse.move(400, 300);
      await page.mouse.wheel(0, 600);
      await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(100);
      await page.evaluate(() => window.lenis.scrollTo(0, { immediate: true }));
    }

    // The skip link still hands the focus to the main landmark.
    await page.keyboard.press('Tab');
    await expect(page.locator('.skip-link')).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();
    expect(new URL(page.url()).hash).toBe('#main-content');

    expect(errors()).toEqual([]);
  });

  test('touch devices keep native scrolling', async ({ page, isMobile }) => {
    test.skip(!isMobile, 'touch layout only');

    const errors = watchErrors(page);
    await page.goto('/');

    await expect(page.locator('html')).not.toHaveClass(LENIS);
    expect(await page.evaluate(() => 'lenis' in window)).toBe(false);
    expect(errors()).toEqual([]);
  });

  test.describe('with reduced motion', () => {
    test.use({ reducedMotion: 'reduce' });

    test('native scrolling is left in place', async ({ page, isMobile }) => {
      test.skip(isMobile, 'desktop layout only');

      await page.goto('/');
      await expect(page.locator('html')).not.toHaveClass(LENIS);
      expect(await page.evaluate(() => 'lenis' in window)).toBe(false);
    });
  });

  test('the body class "no-smooth-scroll" opts a project out', async ({ page, isMobile }) => {
    test.skip(isMobile, 'desktop layout only');

    // A project adds the class server-side (body_class); here it is set as soon as the
    // body exists, before the deferred main module runs.
    await page.addInitScript(() => {
      new MutationObserver((mutations, observer) => {
        if (document.body) {
          document.body.classList.add('no-smooth-scroll');
          observer.disconnect();
        }
      }).observe(document, { childList: true, subtree: true });
    });
    await page.goto('/');
    await expect(page.locator('html')).not.toHaveClass(LENIS);
  });
});
