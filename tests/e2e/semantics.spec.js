/* global document */

import { expect, test } from '@playwright/test';

/**
 * Semantic HTML and crawler accessibility of the rendered pages: one h1, a
 * logical heading outline, landmarks, real links, images with alternative
 * text and dimensions, editorial dates, and the essential content without
 * JavaScript. These checks run on the production Playground.
 */
const routes = ['/', '/about/', '/about/team/', '/sample-post-1/', '/category/news/', '/?s=sample'];

async function outline(page) {
  return page.evaluate(() =>
    [...document.querySelectorAll('h1, h2, h3, h4, h5, h6')].map((heading) => ({
      level: Number(heading.tagName.slice(1)),
      text: heading.textContent.trim(),
    })),
  );
}

for (const path of routes) {
  test(`${path}: one h1, no skipped heading levels, landmarks and real links`, async ({ page }) => {
    const response = await page.goto(path);
    expect(response?.status()).toBe(200);

    const headings = await outline(page);
    expect(headings.filter((heading) => heading.level === 1)).toHaveLength(1);
    expect(headings[0].level).toBe(1);

    let previous = 1;
    for (const heading of headings) {
      expect(
        heading.level,
        `heading "${heading.text}" after level ${previous}`,
      ).toBeLessThanOrEqual(previous + 1);
      previous = heading.level;
    }

    await expect(page.locator('html')).toHaveAttribute('lang', /^[a-z]{2}(-[A-Za-z]{2})?$/);
    await expect(page.locator('main')).toHaveCount(1);
    await expect(page.locator('body > header')).toHaveCount(1);
    await expect(page.locator('body > footer')).toHaveCount(1);
    await expect(page.locator('a.skip-link[href="#main-content"]')).toHaveCount(1);

    const unlabelledNavs = await page
      .locator('nav:not([aria-label]):not([aria-labelledby])')
      .count();
    expect(unlabelledNavs).toBe(0);

    // Theme-generated links only: editorial content (the seeded About page has a "#" link) is not the theme's.
    const badLinks = await page.locator('header a, main a, footer a').evaluateAll((links) =>
      links
        .filter((link) => !link.closest('.content'))
        .map((link) => link.getAttribute('href') ?? '')
        .filter((href) => href === '' || href === '#' || href.startsWith('javascript:')),
    );
    expect(badLinks).toEqual([]);

    const imagesWithoutAlt = await page.locator('img:not([alt])').count();
    expect(imagesWithoutAlt).toBe(0);
    const imagesWithoutSize = await page.locator('img:not([width]), img:not([height])').count();
    expect(imagesWithoutSize).toBe(0);
  });
}

test('posts expose machine-readable dates and a public author link', async ({ page }) => {
  await page.goto('/sample-post-2/');

  const time = page.locator('main time[datetime]').first();
  await expect(time).toHaveAttribute('datetime', /^\d{4}-\d{2}-\d{2}T/);
  await expect(page.locator('main a[href*="/author/alice/"]')).toHaveCount(1);
});

test('listings use second-level headings for cards under the page heading', async ({ page }) => {
  await page.goto('/category/news/');
  const headings = await outline(page);

  expect(headings[0]).toMatchObject({ level: 1 });
  expect(headings.slice(1).every((heading) => heading.level === 2)).toBe(true);
});

test.describe('without JavaScript', () => {
  test.use({ javaScriptEnabled: false });

  test('the essential content, navigation and footer are in the HTML', async ({ page }) => {
    await page.goto('/about/');

    await expect(page.locator('main h1')).toHaveText('About us');
    await expect(page.locator('main')).toContainText('A short introduction');
    await expect(page.locator('#primary-navigation a[href$="/about/"]')).toHaveCount(1);
    await expect(page.locator('#primary-navigation a[href$="/about/team/"]')).toHaveCount(1);
    await expect(page.locator('#primary-navigation')).toBeVisible();
    await expect(page.locator('[data-nav-toggle]')).toBeHidden();
    await expect(page.locator('footer')).toContainText('Playground');
  });

  test('a post renders its body, metadata and comments server-side', async ({ page }) => {
    await page.goto('/sample-post-1/');

    await expect(page.locator('main h1')).toContainText('Sample post 1');
    await expect(page.locator('main')).toContainText('Body of post 1.');
    await expect(page.locator('main time[datetime]').first()).toBeVisible();
    // Comments are paginated (two per page, newest page first): at least one comment is in the HTML.
    await expect(
      page
        .locator('main')
        .getByText(/Comment number \d\./)
        .first(),
    ).toBeVisible();
  });
});
