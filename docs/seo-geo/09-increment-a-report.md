# SEO/GEO Foundation — Increment A report (Phases 1–3)

Date: 2026-10-08 · Branch: `feature/seo-geo-foundation` · Baseline: `v2027.1` (`8308adb`) · Commits on the branch: `985a45b` (Phase 0 documents), `a96e2bd` (residue scan exclusion and tooling tests), `76a281d` (Increment A implementation, tests and tooling), plus the documentation commit that contains this report. Nothing was pushed, merged or deployed.

## 1. Executive summary

The theme now ships a native SEO foundation under `app/Seo` that fills the gaps WordPress core leaves when no SEO plugin is installed (meta description, canonicals for listings, Open Graph and Twitter Card, `noindex` on 404 and attachment pages, a leaner core sitemap) and goes silent, signal by signal, when Rank Math, Yoast SEO, SEOPress, All in One SEO, The SEO Framework or Slim SEO is active. An explicit environment policy makes every `local`, `development` or `staging` copy non-indexable through the robots meta directive and an `X-Robots-Tag` header, with or without a plugin, and never guesses from the domain. Diagnostics are available in Site Health and WP-CLI. Everything is configured per site in `config/seo.php`, validated on load, with no admin screens, no new dependencies, no JavaScript and no agency data. All checks are green: 36 unit tests, 5 tooling tests, 29 end-to-end tests on two Playgrounds (production and staging), the full `npm run check` suite on the local site, and a measured provider matrix with Rank Math and Yoast showing zero duplicates.

## 2. Architecture implemented

| Class                                             | Responsibility                                                                                                                                                                                  | Hooks                                                                          |
| ------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| `Seo\Seo`                                         | Entry point registered in `Theme::SERVICES`; loads the configuration, resolves provider, page context, ownership and environment once per request; gates everything to real front-end requests. | `after_setup_theme`                                                            |
| `Seo\Config`                                      | Defaults ← `config/seo.php` ← `<slug>/seo/config` filter; schema validation with issues for the diagnostics.                                                                                    | —                                                                              |
| `Seo\Provider`                                    | Enum of recognised plugins; detection by version constant (inactive plugins define nothing).                                                                                                    | —                                                                              |
| `Seo\Signal`, `Seo\Owner`, `Seo\Ownership`        | Per-request owner of title, description, canonical, robots, social, sitemap and `robots.txt`: core, provider, theme or none; `with_provider` opt-ins.                                           | —                                                                              |
| `Seo\PageKind`, `Seo\PageContext`                 | What the request is (front page, posts index, singular with status and password, term, author, date, post type archive, search, 404), pagination, preview.                                      | read at first use after `wp`                                                   |
| `Seo\Text`                                        | Pure text helpers: HTML to plain text, bounded summaries, host extraction.                                                                                                                      | —                                                                              |
| `Seo\Meta\Description`                            | Excerpt → page lead → content; term, author and post type descriptions; nothing on paginated, protected, unpublished or preview content.                                                        | —                                                                              |
| `Seo\Meta\Canonical`                              | Canonicals for the front page, posts index, archives and paginated listings from permalink functions; removes the comment page core appends when `cpage` was not requested.                     | `get_canonical_url`                                                            |
| `Seo\Meta\Social`                                 | Open Graph and Twitter Card data, featured image or configured default image with dimensions and alt, article times.                                                                            | —                                                                              |
| `Seo\Meta\Head`                                   | The single head callback printing description, canonical and social tags when the theme owns them.                                                                                              | `wp_head` priority 2                                                           |
| `Seo\Indexability\Environment`, `Verdict`         | Pure environment decision from `WP_ENVIRONMENT_TYPE`, its declaration state, the home host and the optional production host list.                                                               | —                                                                              |
| `Seo\Indexability\Policy`                         | Page-type directives while core owns robots; `noindex, nofollow` on non-production through `wp_robots` (core, Yoast) and `rank_math/frontend/robots` (Rank Math); the header.                   | `wp_robots` (`PHP_INT_MAX`), `rank_math/frontend/robots`, `wp_headers`         |
| `Seo\Sitemap`                                     | Core sitemap filters: users list off, `noindex` types and taxonomies removed.                                                                                                                   | `wp_sitemaps_add_provider`, `wp_sitemaps_post_types`, `wp_sitemaps_taxonomies` |
| `Seo\Diagnostics\Report`, `SiteHealth`, `Command` | Read-only report; Site Health → Info section; `wp <slug> seo report [--format=json]`.                                                                                                           | `debug_information`, WP-CLI                                                    |

Request lifecycle, defaults and the behaviour per signal are documented in `03-configuration.md`; the design decisions in `02-implementation-plan.md` (section 15 lists the deviations).

## 3. Files created and modified

Created: `app/Seo/**` (19 classes and enums), `config/seo.php`, `tests/unit/` (runner, bootstrap, 7 test files), `tests/scripts/scan.test.mjs`, `tests/e2e/seo.spec.js`, `tests/e2e/seo-staging.spec.js`, `tests/e2e/seo-helpers.js`, `tests/seo-report.mjs`, `tests/stubs/wp-cli.stub`, `docs/seo-geo/03-configuration.md`, `docs/seo-geo/08-head-reports/*.md`, this report.

Modified: `app/Core/Theme.php` (service registration), `tests/smoke.php` (SEO checks), `tests/playground/seed.php` (two posts per page so listings paginate), `scripts/playground.mjs` (`--env`), `playwright.config.js` (staging Playground and project), `scripts/scan.mjs` (`docs/` development-only), `scripts/check.mjs` (tooling tests step), `scripts/release.mjs` (`config/` ships), `phpstan.neon.dist`, `composer.json` (`test:unit`), `package.json` (`test:scripts`), `.github/workflows/ci.yml` (unit tests in the PHP job), `.prettierignore` (generated head reports), `languages/*.pot`, `README.md`, `CLAUDE.md`, `docs/seo-geo/00-audit.md`, `01-ownership-matrix.md`, `02-implementation-plan.md`.

Untouched: templates, styles, scripts, blocks, ACF groups, `functions.php`, `index.php`.

## 4. Ownership at runtime

| Signal                | No plugin                           | Rank Math / Yoast / other provider              |
| --------------------- | ----------------------------------- | ----------------------------------------------- |
| Title                 | Core                                | Provider                                        |
| Description           | Theme (fallback)                    | Provider (`with_provider` can keep the theme's) |
| Canonical             | Core on singular, theme on listings | Provider                                        |
| Robots, page types    | Core + theme directives             | Provider                                        |
| Robots, environment   | Theme, always                       | Theme, always (through the provider's channel)  |
| Social                | Theme (fallback)                    | Provider                                        |
| Sitemap, `robots.txt` | Core (+ theme sitemap filters)      | Provider                                        |

## 5. Enabled and disabled by default

Enabled: environment policy (`noindex, nofollow` + header on non-production), description/canonical/social fallbacks without a plugin, `noindex` on 404 and attachment pages, users sitemap removed, `summary_large_image` cards when an image exists, `post` as article type. Disabled or empty: `with_provider`, `production_hosts`, extra `noindex` types, `twitter:site`, default social image, forced provider. Not implemented yet (next increments): structured data, breadcrumbs, crawler policy, WPML and WooCommerce compatibility, `llms.txt` (not planned).

## 6. Examples of generated output

Local site (no plugin, `WP_ENVIRONMENT_TYPE=local`), single post with a featured image during the verification:

```html
<meta name="robots" content="noindex, nofollow" />
<meta
  name="description"
  content="Benvenuto in WordPress. Questo è il tuo primo articolo. Modificalo o cancellalo e inizia a scrivere!"
/>
<meta property="og:type" content="article" />
<meta property="og:title" content="Ciao mondo! – understack-wp-theme" />
<meta
  property="og:description"
  content="Benvenuto in WordPress. Questo è il tuo primo articolo. Modificalo o cancellalo e inizia a scrivere!"
/>
<meta property="og:url" content="http://localhost:10034/ciao-mondo/" />
<meta property="og:site_name" content="understack-wp-theme" />
<meta property="og:locale" content="it_IT" />
<meta
  property="og:image"
  content="http://localhost:10034/wp-content/uploads/2026/10/seo-social-test-1600x1067.jpg"
/>
<meta property="og:image:width" content="1600" />
<meta property="og:image:height" content="1067" />
<meta property="og:image:alt" content="Blue test image" />
<meta property="article:published_time" content="2026-09-03T14:59:09+02:00" />
<meta property="article:modified_time" content="2026-09-03T14:59:09+02:00" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="Ciao mondo! – understack-wp-theme" />
<meta
  name="twitter:description"
  content="Benvenuto in WordPress. Questo è il tuo primo articolo. Modificalo o cancellalo e inizia a scrivere!"
/>
<meta
  name="twitter:image"
  content="http://localhost:10034/wp-content/uploads/2026/10/seo-social-test-1600x1067.jpg"
/>
<link rel="canonical" href="http://localhost:10034/ciao-mondo/" />
```

Response header on the same copy: `X-Robots-Tag: noindex, nofollow`. Category archive: `<link rel="canonical" href="http://localhost:10034/category/news/">` plus `og:type website`, no description without a term description. Search and 404: core robots only, nothing from the theme. With Rank Math active the robots tag becomes `<meta name="robots" content="noindex, nofollow"/>` inside Rank Math's block and the theme prints nothing else; with Yoast the core tag reads `noindex, nofollow` and Yoast prints its own description, canonical and social tags. Full reports: `08-head-reports/`.

## 7. Tests executed

| Command                                                                                              | Result                                                                                                                                                                                                                                                                                                                                                     |
| ---------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `composer test:unit`                                                                                 | 36 passed (configuration, provider detection, ownership, environment policy, robots merging, summaries, pagination URLs).                                                                                                                                                                                                                                  |
| `npm run test:scripts`                                                                               | 5 passed (residue scan exclusions).                                                                                                                                                                                                                                                                                                                        |
| `npm run test:e2e` (CI mode, two Playgrounds)                                                        | 29 passed, 3 skipped (mobile project skips by design): existing smoke, navigation and login specs plus `seo.spec.js` (home, paginated listing, post, explicit comment page, page, category, search, 404, robots.txt, sitemap) and `seo-staging.spec.js` (noindex meta and header on every template, fallbacks kept, robots.txt untouched, sitemap served). |
| `npm run check -- --url http://localhost:10034`                                                      | 12/12: PHP syntax (70 files), Composer, autoloader, Vite manifest, ESLint, Prettier, PHPCS, PHPStan level 5, residue scan, tooling tests, WordPress smoke test (with the new SEO checks), HTTP smoke test.                                                                                                                                                 |
| Provider matrix on the local site (`tests/seo-report.mjs`, one plugin at a time, permalinks flushed) | No plugin, Rank Math 1.0.280, Yoast SEO 28.6: zero duplicates on every route; `noindex, nofollow` meta and header present in all three states; `wp <slug> seo report` correct in each state.                                                                                                                                                               |
| Social image verification                                                                            | Temporary 2400×1600 upload: `wide` served 1600×1067 (10 KB JPEG) with width, height and alt; `summary_large_image` card; upload removed afterwards.                                                                                                                                                                                                        |

Not executed: the GitHub Actions run (no push authorised), WPML (unavailable), WooCommerce (Increment D), SEOPress / AIOSEO / TSF / Slim SEO behaviour (detected, not measured), Lighthouse and Google validators (not claimed).

## 8. Residual risks and limitations

- Rank Math stays dormant until its registration is completed or skipped; the theme treats it as present (reliable constant) and the diagnostics warn. Documented in `03-configuration.md`.
- After activating or deactivating an SEO plugin the permalinks must be flushed, otherwise `/wp-sitemap.xml` can answer 404 (stale rewrite rules). Documented.
- The environment header covers only responses served by WordPress; static files on staging need the server. Documented in the checklist.
- A front page that lists posts has no description (nothing editorial to summarise). Static front pages use excerpt, lead or content.
- `with_provider` opt-ins deliberately produce duplicates; they ship empty.
- Providers other than Rank Math and Yoast are handled by detection only.
- CI has not run on this branch yet; the same commands pass locally.

## 9. Configuration required per client

Optional, all in `config/seo.php`: `indexability.production_hosts` (recommended), `social.twitter_site`, `social.default_image`, extra `noindex` types. Mandatory outside the theme: `WP_ENVIRONMENT_TYPE` declared on every copy; staging protected at the server. Nothing is pre-filled.

## 10. Git state

Branch `feature/seo-geo-foundation`, four commits ahead of `v2027.1`, working tree clean after the documentation commit. No push, no merge, no tag, no deploy.

## 11. Acceptance criteria of Increment A

| Criterion                                             | Evidence                                                                                                                                          |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| No regression against `v2027.1`                       | All baseline checks and the original Playwright specs pass; templates, styles and scripts untouched.                                              |
| Existing tests green                                  | `npm run check` 12/12, Playwright 29 passed.                                                                                                      |
| Compatibility with core, Rank Math and Yoast          | Measured matrix in `08-head-reports/`: no duplicates, environment policy preserved.                                                               |
| No duplicate meta tags                                | Asserted by `expectNoDuplicates` on every e2e route and by the head reports.                                                                      |
| Predictable, tested production/staging/local handling | Unit tests for every environment rule; staging Playground in CI; local site verified (`local` → noindex); contradictions reported, never guessed. |
| No agency-specific data in defaults                   | `config/seo.php` returns an empty array; residue scan passes.                                                                                     |
| No visual change                                      | No template, CSS or JavaScript modified.                                                                                                          |
| Documentation updated                                 | `03-configuration.md`, README, CLAUDE.md, updated Phase 0 documents, head reports, this report.                                                   |
| Git state and test results reported precisely         | Sections 7 and 10.                                                                                                                                |

## 12. Open points for Increment B (structured data)

1. Identity fields for the ACF Site Settings tab: confirm the list in `02-implementation-plan.md` §5 (type, names, logo, contacts, address, geo, opening hours, price range, areas served, default social image, Twitter handle; `sameAs` from the existing social links).
2. Author data: the `Person` node must skip display names that look like e-mail addresses and never emit e-mails (the local administrator's display name was an e-mail address until this increment).
3. `AboutPage` / `ContactPage` mapping: configuration file by page path (planned) or an editorial select on pages (new field).
4. Breadcrumb partial wired into `sections/page-header.twig` behind the flag (approved D-04) — confirm the position and the home label.
