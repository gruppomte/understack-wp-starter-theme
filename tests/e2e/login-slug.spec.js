import { expect, test } from '@playwright/test';
import { watchErrors } from './helpers.js';

/**
 * The login-slug must-use plugin (site/mu-plugins) moves the login screen to
 * /<slug>/. The Playground site installs it with the slug from .env.local;
 * against another site (BASE_URL) only the anonymous checks run.
 */
const slug = process.env.WP_LOGIN_SLUG || '_admin';
const account = process.env.BASE_URL
  ? null
  : {
      user: process.env.PLAYGROUND_USER || 'admin',
      password: process.env.PLAYGROUND_PASSWORD || 'password',
    };

test.describe('login slug', () => {
  test('login screen lives at the slug; core login URLs answer 404', async ({ page }) => {
    const errors = watchErrors(page);

    const login = await page.goto(`/${slug}/`);
    expect(login?.status()).toBe(200);
    await expect(page.locator('#loginform')).toBeVisible();
    await expect(page.locator('#loginform')).toHaveAttribute('action', new RegExp(`/${slug}/`));
    // The 404 responses below are logged by the browser as failed resources.
    expect(errors()).toEqual([]);

    expect((await page.goto('/wp-login.php'))?.status()).toBe(404);
    expect((await page.goto('/wp-admin/'))?.status()).toBe(404);
    expect((await page.goto('/wp-admin'))?.status()).toBe(404);
    expect((await page.goto('/wp-admin/edit.php'))?.status()).toBe(404);

    await page.goto(`/${slug}/?action=lostpassword`);
    await expect(page.locator('#lostpasswordform')).toBeVisible();
    expect(page.url()).not.toContain('wp-login.php');

    await page.goto(`/${slug}/?action=rp&key=invalid&login=nobody`);
    expect(page.url()).toContain(`/${slug}/`);
    expect(page.url()).not.toContain('wp-login.php');
  });

  test('redirect_to, admin access and logout stay on the slug', async ({ page, isMobile }) => {
    test.skip(!account, 'needs the Playground test account');
    // WordPress stores session tokens with a read-modify-write on user meta:
    // two projects logging in as the same user at the same time can lose a
    // session. The admin flow is layout-independent, so one project is enough.
    test.skip(isMobile, 'runs on the desktop project only');

    await page.goto(`/${slug}/?redirect_to=${encodeURIComponent('/about/')}`);
    await page.fill('#user_login', account.user);
    await page.fill('#user_pass', account.password);
    await page.click('#wp-submit');
    await expect(page).toHaveURL(/\/about\/$/);

    expect((await page.goto('/wp-admin/'))?.status()).toBe(200);
    // The admin menu collapses on small screens; the toolbar is always rendered.
    await expect(page.locator('body.wp-admin #wpadminbar')).toBeVisible();

    await page.goto(`/${slug}/`);
    await expect(page).toHaveURL(/\/wp-admin\//);

    const logout = await page.locator('#wp-admin-bar-logout a').getAttribute('href');
    expect(logout).toContain(`/${slug}/`);
    expect(logout).not.toContain('wp-login.php');

    await page.goto(logout);
    await expect(page.locator('#loginform')).toBeVisible();
    expect(page.url()).toContain(`/${slug}/`);
    expect((await page.goto('/wp-admin/'))?.status()).toBe(404);
  });
});
