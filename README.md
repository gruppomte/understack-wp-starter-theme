# Understack WP Starter

A WordPress theme base for custom, professionally developed sites: Timber 2 and Twig for templates, ACF Pro for structured content and blocks, Tailwind CSS 4 and Vite for the front end, Alpine.js and GSAP loaded on demand. It targets WordPress 6.7+ and PHP 8.3+.

Every client project starts from this codebase and becomes independent after `npm run setup` (see "Starting a project").

`UNDERSTACK.md` lists the conventions that must not regress and the definition of done; developers and coding agents read it before changing the theme. It is development-only: it never ships and the residue scan skips it.

## Requirements

- PHP 8.3 or newer, Composer 2
- Node.js 22.17 or newer, npm
- WordPress 6.7 or newer
- LocalWP (or any local WordPress) for development

## Quick start

```bash
composer install
npm ci
npm run build        # or: npm run dev
```

Activate the theme in Appearance → Themes (or `wp theme activate <slug>`). ACF Pro is bundled in `includes/acf`; activate its license by defining `ACF_PRO_LICENSE` in `wp-config.php`. The key is never stored in the theme.

`npm run dev` starts the Vite dev server: assets are served from it (CSS hot reload, page reload on Twig/PHP changes) only while `wp_get_environment_type()` is `local` or `development`. Stop it and the compiled files in `public/build` are used again.

### Local site conventions

Copy `.env.example` to `.env.local` (ignored by Git), fill in your development email and the local password and run `npm run local:setup` once per local site. It is idempotent and only runs on sites whose environment type is `local` or `development`:

- the login screen moves to `/<WP_LOGIN_SLUG>/` (default `_admin`): the must-use plugin in `site/mu-plugins/login-slug.php` is copied to `wp-content/mu-plugins/` and `WP_LOGIN_SLUG` is added to `wp-config.php`. `_admin` is a URL, never a username;
- the existing Administrator (never renamed, never duplicated) gets the development email from `WP_ADMIN_EMAIL`, which also becomes the Administration Email Address;
- on the first run only, that account receives `WP_ADMIN_PASSWORD` (`--reset-password` applies it again). Nothing at runtime touches passwords, and the script refuses staging and production sites.

The Playground test site installs the same plugin and slug, so the end-to-end suite covers the login flows.

## Commands

| Command                                                          | Purpose                                                                                                  |
| ---------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `npm run dev` / `npm run build`                                  | Vite dev server / production build to `public/build`                                                     |
| `npm run lint`                                                   | ESLint and PHP_CodeSniffer                                                                               |
| `npm run analyse`                                                | PHPStan (level 5, WordPress and ACF stubs)                                                               |
| `npm run format` / `npm run format:check`                        | Prettier                                                                                                 |
| `npm run check -- --url http://site.local`                       | Everything above plus the Vite manifest, residue scan, the PHP smoke test and the HTTP smoke test        |
| `npm run test:e2e`                                               | Playwright end-to-end tests (boots a disposable WordPress Playground, or set `BASE_URL`)                 |
| `npm run wp:playground`                                          | Run the theme in WordPress Playground with sample content                                                |
| `npm run local:setup`                                            | Configure the local WordPress site: login slug plugin and development administrator (reads `.env.local`) |
| `npm run lighthouse -- --url http://site.local`                  | Lighthouse CI against a running site                                                                     |
| `npm run i18n`                                                   | Regenerate `languages/<text-domain>.pot`                                                                 |
| `npm run acf:install -- /path/to/advanced-custom-fields-pro.zip` | Update the bundled ACF Pro                                                                               |
| `npm run acf:json` / `npm run acf:json:sync`                     | Local JSON status / import into the database (needs WP-CLI)                                              |
| `npm run setup`                                                  | Initialise a client project (see below)                                                                  |
| `npm run release`                                                | Build the installable ZIP in `dist/`                                                                     |

Composer equivalents: `composer lint`, `composer analyse`, `composer test`.

## Architecture

```
app/            PHP, PSR-4 namespace, one responsibility per class
  Core/         Theme (composition root), Setup, Assets, Timber, Router, Editor, PageTemplates
  Controllers/  one per template type: prepare data, render a Twig template
  Context/      data shared by every template (menus, site options)
  Services/     data preparation (for example SiteOptions)
  ACF/          bundled ACF loader, Local JSON, options pages
  Blocks/       block registration and rendering
  Hooks/        front-end hooks
  Integrations/ optional integrations (WooCommerce)
  Seo/          SEO/GEO foundation: configuration, provider detection, fallbacks, indexability, diagnostics
  Support/      small helpers (Image, Posts, ViteManifest, Environment, base classes)
blocks/         one directory per block: block.json, render.php, block.twig, style.css
views/          Twig templates: layouts, pages, singles, archives, partials, components, sections, system
src/            CSS and JavaScript sources (Vite)
acf-json/       ACF field groups (Local JSON)
config/         per-site configuration files (seo.php)
includes/acf/   bundled ACF Pro (third-party, never edited)
tests/          smoke tests, Playwright suite, Playground seed
site/           site-level files installed next to the theme (login-slug must-use plugin)
scripts/        Node tooling (setup, release, scan, check, i18n)
```

### PHP

`functions.php` only loads the Composer autoloader and calls `Theme::boot()`. `Theme::SERVICES` lists the classes that register hooks; each implements `Registrable` and stays small. `index.php` routes every request to a controller that renders Twig through Timber.

The theme owns presentation. Functionality that must survive a theme switch (custom post types with business meaning, API integrations, leads, webhooks, cron jobs, domain REST endpoints) belongs in a site plugin, not in `app/`. The `Support\PostType` and `Support\Taxonomy` base classes are for presentation-only types; use them only when the type is meaningless without this theme.

### Timber and Twig

PHP prepares data, Twig renders it. Controllers and services build the context; templates never query the database or transform data. `function()` calls in Twig are limited to WordPress output helpers (`wp_head`, `comment_form`, password form).

Twig autoescape is off (Timber's default) because WordPress already stores titles, names and content with HTML entities and HTML; Twig's HTML escaper would double-encode them. The rules instead:

- escape at output: `{{ value|e }}` for plain text, `{{ url|esc_url }}`, `{{ attribute|esc_attr }}`, `{{ html|wp_kses_post }}` for untrusted HTML;
- WordPress-provided HTML (`post.content`, `post.title`, `post.excerpt`, menu titles) is printed as is, exactly like core templates;
- ACF and user data is sanitised in PHP (see `Services\SiteOptions`) and escaped again in templates;
- `|raw` is not used; if a case ever needs it, document why next to it.

Components under `views/components` accept explicit parameters and are included with `only`.

### ACF

Field groups live in `acf-json/` (Local JSON) and are versioned. Edit them locally, commit the JSON, deploy: the field group UI is hidden in production (`acf/settings/show_admin`) so the database cannot drift from the files. Field values are read by name, never by key. Site-wide content is on the "Site Settings" options page; its values are autoloaded and sanitised in `Services\SiteOptions`.

Update the bundled plugin with `npm run acf:install -- <zip>`. Keep `includes/acf` untouched otherwise.

### Blocks

Blocks are ACF blocks registered from `blocks/<name>/block.json` (apiVersion 3) by `Blocks\Blocks`:

- `render.php` delegates to `Blocks\Renderer`, which builds the Timber context (`fields`, `block`, `is_preview`, `wrapper_attributes`) and renders `block.twig` (Twig namespace `@blocks`);
- `style.css`, `editor.css` and `view.js` are optional Vite entries attached to the block through the manifest, so they load only where the block renders (front end and editor) thanks to `should_load_separate_core_block_assets`;
- fields are a normal ACF field group located on the block (`acf-json/group_block_<name>.json`);
- `example` in `block.json` feeds the inserter preview.

Block names, attributes and field keys are a public contract with saved content: never rename them once a block is in use. `Core\Editor` can restrict the inserter per project (`ALLOWED_BLOCKS` or the `<slug>/editor/allowed_blocks` filter); by default every block is available and theme.json limits colours, sizes and spacing to the design tokens.

### Design tokens: theme.json and Tailwind

`theme.json` is the single source of design tokens (palette, font family, font sizes, spacing scale, content and wide widths). WordPress exposes them as `--wp--preset-*` custom properties on the front end and inside the editor; `src/css/theme.css` maps them into Tailwind with `@theme inline`, so `bg-accent`, `text-display` or `max-w-page` resolve to the same values the block editor offers. Change a colour in theme.json and both sides follow. Tailwind-only tokens (line heights, breakpoints) stay in `theme.css`. Custom colours, font sizes and spacing are disabled for editors.

### Vite, scripts and styles

`Core\Assets` reads `public/build/.vite/manifest.json` and enqueues the hashed files; nothing is hardcoded. The main script is a WordPress script module (`wp_enqueue_script_module`), so it is deferred, dependency-aware and preloaded from the head. Feature modules (`src/js/modules`, `src/js/animations`) are dynamic imports loaded only when the page contains the markup they handle.

- Theme behaviour (navigation, modals, animations, Alpine components): Vite ES modules in `src/js`.
- Block front-end behaviour: `blocks/<name>/view.js`, registered as a script module and loaded only where the block renders.
- WordPress Script Modules API: used for both of the above; keep classic `wp_enqueue_script` for third-party scripts and for WordPress dependencies such as `comment-reply`.

Third-party scripts (analytics, maps, chat) are not included. Add them with `Assets::enqueueExternal($handle, $src, $attributes, $strategy)`: it enqueues the script async in the footer and lets a consent manager gate it through attributes such as `['type' => 'text/plain', 'data-cookieconsent' => 'statistics']`.

### Interactivity: Alpine, Interactivity API, GSAP

- Theme UI state (dropdowns, accordions, tabs, toggles, modal state): Alpine.js. It is bundled but loaded only when a `[x-data]` element is on the page. Projects with a strict Content Security Policy should switch `src/js/modules/alpine.js` to `@alpinejs/csp`.
- Interactive, reusable Gutenberg blocks: evaluate the WordPress Interactivity API first (`viewScriptModule` with `@wordpress/interactivity`).
- Complex animation: GSAP, in `src/js/animations`, loaded only when `data-animate`, `data-animate-stagger`, `data-hero` or `data-parallax` markup exists.

Animations respect `prefers-reduced-motion` and never hide content before JavaScript runs: scroll reveals apply only to elements below the fold at load, animate opacity (content stays in the accessibility tree) and complete when an element receives focus. Keep the LCP element (main heading, hero image) out of entrance animations.

### Images

`components/image.twig` renders attachments through `wp_get_attachment_image()`: srcset, sizes, width and height, alt text from the media library, `decoding="async"`. Pass `priority: true` for the LCP image (eager loading, `fetchpriority="high"`) and `loading: 'eager'` for images in the initial viewport; everything else is lazy. Listings prime the thumbnail and term caches (`Support\Posts`) so a grid never runs one query per card.

### Internationalisation

Strings use the theme text domain in PHP and Twig (`__()`, `_n()`, …). `npm run i18n` regenerates the `.pot` file; the initializer renames the text domain.

### SEO and GEO

The theme ships an SEO/GEO foundation (`app/Seo`) that never competes with WordPress core or with an SEO plugin. Without a plugin it adds what core leaves out: a meta description from the excerpt, the page lead or the content; canonicals for the front page, archives and paginated listings; Open Graph and Twitter Card metadata; `noindex` on 404 and attachment pages; a core sitemap without the users list. When Rank Math, Yoast SEO, SEOPress, All in One SEO, The SEO Framework or Slim SEO is active, the theme is silent for every signal the plugin owns.

The environment policy is always on: a copy that declares `WP_ENVIRONMENT_TYPE` as `local`, `development` or `staging` serves `noindex, nofollow` as a meta directive and as an `X-Robots-Tag` header, also through Rank Math and Yoast. Production is never guessed from the domain; a missing or contradictory declaration is reported by the diagnostics (Site Health → Info, or `wp <slug> seo report`) instead of being acted on. Real staging protection (HTTP authentication, firewall) stays at the server.

Crawlers: `robots.txt` stays the WordPress (or plugin) file; `config/seo.php` can record the owner's decision (`crawlers.policy`) and append groups for specific user agents, AI training crawlers included. The theme ships no vendor list; `docs/seo-geo/05-crawler-policy.md` has the verified tokens and examples. The diagnostics (Site Health → Info, `wp <slug> seo report`) rate every check PASS, WARNING, FAIL or NOT VERIFIED and never call external services; WPML compatibility is designed and mock-tested, WooCommerce compatibility verified locally (`docs/seo-geo/07-compatibility.md`).

Structured data: without a plugin the theme prints one JSON-LD graph per page (WebSite, the organisation configured in Site Settings → Organization, the page, BlogPosting/Article with public authors only, BreadcrumbList when breadcrumbs are enabled). Types are explicit, nothing is invented, authors are never derived from logins or e-mails, and About/Contact pages are typed only through the page field or configured IDs. An optional breadcrumb trail (`breadcrumbs.enabled`) renders in the page header without changing the layout when off.

Settings live in `config/seo.php` (or the `<slug>/seo/config` filter); the reference is in `docs/seo-geo/03-configuration.md`, the structured data in `04-structured-data.md`, the design and the measured plugin behaviour in the other `docs/seo-geo` documents.

### WooCommerce

The integration layer (`Integrations\WooCommerce`, `woocommerce.php`, `views/woocommerce`, `src/css/woocommerce.css`) is inert unless the plugin is active and can be removed by the initializer. Customisations go through WooCommerce hooks; template overrides only when hooks are not enough.

## Security

Every input follows the same pipeline: validate, sanitise, authorise, escape. The theme itself registers no forms, Ajax or REST endpoints; when a project adds one:

- validate and sanitise all input (`sanitize_text_field()`, `absint()`, `esc_url_raw()`, `wp_kses_post()`), rejecting invalid values rather than cleaning them blindly;
- check a nonce and a capability (`current_user_can()`) for every state-changing action; a nonce alone is not authorisation;
- give REST routes a real `permission_callback`, argument validation and schema;
- escape at output in the template.

No SVG uploads are enabled, no secrets live in the repository, and the Vite dev server is only ever used in local environments. Security headers (CSP, HSTS, `X-Frame-Options`) belong to the web server or CDN, not to the theme.

## Performance

Principles: ship little JavaScript (the main bundle is a few KB; Alpine and GSAP load on demand), one stylesheet, no render-blocking third-party assets, images sized per placement, no query per item in loops, template caching for Twig outside local environments. Recommended budgets per page, to adapt per project:

| Metric                    | Budget                       |
| ------------------------- | ---------------------------- |
| LCP                       | ≤ 2.5 s                      |
| INP                       | ≤ 200 ms                     |
| CLS                       | ≤ 0.1                        |
| JavaScript transferred    | ≤ 100 KB (gzip)              |
| CSS transferred           | ≤ 50 KB (gzip)               |
| DOM size                  | ≤ 1500 nodes                 |
| Database queries per page | ≤ 40 without an object cache |

`npm run lighthouse` runs Lighthouse CI with these intentions (accessibility is the only hard assertion). Page cache, OPcache and a persistent object cache (Redis) are recommended for medium and large sites; the theme works without them and never assumes Redis.

## Testing and CI

- `npm run check -- --url …`: syntax, Composer, manifest, linters, PHPStan, residue scan, tooling tests, PHP smoke test (theme bootstrap, Timber, ACF, templates, SEO foundation) and HTTP smoke test (homepage, page, post, archive, search, 404).
- `composer test:unit`: unit tests for the pure PHP logic (configuration validation, provider detection, ownership, environment policy, text summaries, pagination URLs) without WordPress.
- `npm run test:scripts`: `node:test` suite for the Node tooling (residue scan).
- `npm run test:e2e`: Playwright. Without `BASE_URL` it boots two WordPress Playgrounds (PHP in WebAssembly, no Docker) with `tests/playground/seed.php`: one as production, one declaring `WP_ENVIRONMENT_TYPE=staging` for the indexability checks. With `BASE_URL` it runs against any site (set `SEO_ENVIRONMENT=production` or `staging` to include the SEO specs there). Covers the homepage, navigation, mobile menu, sub-menus, 404, console errors and the SEO head output.
- `node tests/seo-report.mjs --url …`: Markdown report of the head output of a running site (titles, descriptions, canonicals, robots, social metadata, JSON-LD, headings, landmarks, duplicates); keep the reports for the local site with and without SEO plugins in `docs/seo-geo/08-head-reports`.
- `.github/workflows/ci.yml` runs lint, analysis, unit tests, build, tooling tests and the end-to-end suite (two Playgrounds) on every push to `main` or to a `feature/**` branch and on pull requests.

Project-specific tests belong in the project; the suite here is the baseline.

## Logging and debugging

Development: `WP_DEBUG` and `WP_DEBUG_LOG`; PHP warnings fail the smoke tests. The theme never logs in production and never prints stack traces. Integrations with external services belong in a plugin with their own logger and explicit error handling (no empty `catch` blocks).

## Deployment

`npm run release` produces `dist/<slug>.zip`: runtime files, compiled assets, production Composer dependencies and bundled ACF Pro, without `node_modules`, sources or tooling. It refuses a dirty working tree unless `--allow-dirty` is passed and prints the SHA-256 of the archive. The ZIP installs from Appearance → Themes → Upload and needs neither Composer nor npm on the server. Deploying from Git works as well: run `composer install --no-dev` and `npm ci && npm run build` in the pipeline.

The login slug is site-level, not part of the theme ZIP: deploy `site/mu-plugins/login-slug.php` to `wp-content/mu-plugins/` and define `WP_LOGIN_SLUG` in the production `wp-config.php` (pretty permalinks required). Without the constant the plugin stays inactive. It hides the login screen; brute-force limits and two-factor authentication remain necessary.

Server-side recommendations (outside the theme): HTTPS with HSTS, Brotli or gzip, HTTP/2 or HTTP/3, long `Cache-Control` for `public/build/assets/*` (hashed, immutable), page cache, a real cron job replacing WP-Cron for sites with important scheduled tasks, security headers at the web server or CDN. In `wp-config.php`: `WP_ENVIRONMENT_TYPE`, `WP_DEBUG` off, `DISALLOW_FILE_EDIT`, `WP_MEMORY_LIMIT` as needed, `DISABLE_WP_CRON` when a system cron is in place, and the object cache drop-in when Redis is available.

Useful WP-CLI commands during deployment: `wp theme activate <slug>`, `wp cache flush`, `wp rewrite flush`, `wp search-replace` (with `--dry-run` first) for domain migrations.

## Starting a project

```bash
npm run setup                                  # interactive
npm run setup -- --name "Client Name" --yes    # defaults
```

The initializer renames the theme (directory, package names, theme header, text domain, PHP namespace, asset handles, block text domains), optionally removes WooCommerce, GSAP, Alpine or ACF, leaves out the base's own history in `docs/seo-geo` (audit, implementation plan, increment and release reports, head reports) while keeping the reference documents, reinstalls dependencies, builds, and runs the residue scan: the resulting project contains no reference to this base and is ready to version on its own. Re-running it on an initialised project renames it again.
