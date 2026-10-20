# SEO/GEO Foundation — Increment C report (crawlers, semantics, compatibility, diagnostics)

Date: 2026-10-08 · Branch: `feature/seo-geo-foundation` · Commits of this increment: `616a743` (crawler policy), `6c9532b` (WPML and WooCommerce compatibility), `cae30dc` (diagnostics with statuses), `8592bfa` (semantic, crawler and WPML mock tests, head report), plus the documentation commit that contains this report. Nothing was pushed, merged, tagged or deployed; the remote CI run is still pending for the whole branch.

## 1. Preliminary verification

- Working tree clean on `8eb8c71`; audit, matrix, plan and the A and B reports re-read; no existing crawler policy, WPML or WooCommerce SEO code and no diagnostic statuses were present (only the `is_woocommerce()` guard in the head output and the `<slug>/seo/page_id` filter prepared in B, both reused).
- Difference from the original roadmap, as decided by the approval of Increment C: WPML and WooCommerce compatibility (Phase 7) moved from Increment D into C, together with the diagnostics statuses. Increment D keeps the performance, accessibility and security pass (Phase 8), the full QA matrix (Phase 9) and the operations documentation and delivery (Phase 10). No other substantial difference.
- Principles unchanged: WordPress core first, the SEO plugin owns its signals, the theme is the fallback.

## 2. Architecture

| Component                                         | Responsibility                                                                                                                                                                                                                                           |
| ------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Seo\Crawlers\Groups`                             | Validation and rendering of robots.txt groups from the configuration (tokens, paths, crawl delay); pure. The theme ships no user-agent list.                                                                                                             |
| `Seo\Crawlers\Policy`                             | Appends the groups to the virtual robots.txt after the core or plugin body (`robots_txt`, priority 20) when `crawlers.policy = custom`.                                                                                                                  |
| `Seo\Config`                                      | New keys `crawlers.policy` (`unset` / `open` / `custom`) and `crawlers.groups`.                                                                                                                                                                          |
| `Seo\Integrations\Wpml`                           | Detected through WPML's `SitePress` class; maps configured About/Contact page IDs through `wpml_object_id`; nothing else needed because every URL and identifier already comes from language-aware functions.                                            |
| `Seo\Integrations\WooCommerce`                    | Allowances per page role: shop pages keep description and social tags but get no graph and no breadcrumbs; cart, checkout and account pages get nothing; the shop description comes from the shop page itself.                                           |
| `Seo\Diagnostics\Checks`, `Status`                | Pure rules turning the measured facts into checks rated PASS, WARNING, FAIL or NOT VERIFIED; six checks are NOT VERIFIED by design (server headers, robots.txt as served, user-initiated AI fetchers, rendered HTML, indexing status, CDN and firewall). |
| `Seo\Diagnostics\Report`, `SiteHealth`, `Command` | Facts plus checks; one Site Health field per check with a summary; `wp <slug> seo report` prints checks, facts and summary and exits with 1 on a FAIL. No network request.                                                                               |
| `tests/seo-report.mjs`                            | Now reports `html lang`, the heading outline (skipped levels), images without `alt`, links without a real `href`.                                                                                                                                        |

Details: `05-crawler-policy.md`, `06-content-semantics.md`, `07-compatibility.md`, `03-configuration.md`.

## 3. Files

Created: `app/Seo/Crawlers/{Groups,Policy}.php`, `app/Seo/Integrations/{Wpml,WooCommerce}.php`, `app/Seo/Diagnostics/{Checks,Status}.php`, `tests/unit/{CrawlersTest,ChecksTest,IntegrationsTest}.php`, `tests/e2e/semantics.spec.js`, `docs/seo-geo/{05-crawler-policy,06-content-semantics,07-compatibility}.md`, `docs/seo-geo/08-head-reports/local-woocommerce.md`, this report.

Modified: `app/Seo/{Config,Seo,Breadcrumbs}.php`, `app/Seo/Meta/{Head,Description}.php`, `app/Seo/Diagnostics/{Report,Command,SiteHealth}.php`, `config/seo.php`, `tests/stubs/wp-cli.stub`, `tests/e2e/{seo.spec,seo-staging.spec}.js`, `tests/playground/seo-features.php`, `playwright.config.js`, `tests/seo-report.mjs`, `languages/*.pot`, `docs/seo-geo/{01,02,03}-*.md`, `README.md`, `UNDERSTACK.md`.

No template, stylesheet, script or block changed: the semantic analysis found nothing to fix.

## 4. Crawler matrix and verification limits

The matrix of verified tokens (Google, Bing, OpenAI, Anthropic, Perplexity, Apple), the three kinds of access (search, training, user-initiated fetching) and the example policies are in `05-crawler-policy.md`. What the theme can verify: that the configured groups are rendered after the core block and the `Sitemap:` line (end-to-end test on the staging Playground, head report). What it cannot verify and reports as NOT VERIFIED: robots.txt as served by the web server or CDN, the behaviour of each crawler, user-initiated fetchers (documented by OpenAI and Perplexity as not bound by robots.txt), WAF rules, indexing status. Bot access rules are never changed automatically: the default policy is `unset`, reported as a decision to take; `open` records the decision to allow everything.

## 5. HTML semantics

Template by template analysis in `06-content-semantics.md`: one `h1` per page, no skipped heading levels, `html lang`, one `main`, one `header`, one `footer`, labelled navigations, real links, images with alternative text and dimensions, ISO dates, author links, ACF content rendered in PHP, navigation visible without JavaScript, nothing reserved to bots, nothing generated. Automated by `tests/e2e/semantics.spec.js` on six routes (including a JavaScript-disabled context) and by the extended head report.

## 6. WPML compatibility (designed, mock-tested, not certified)

No duplicate hreflang (the theme emits none), canonicals and identifiers from language-aware functions, breadcrumbs from the translated objects WordPress returns, About/Contact page IDs mapped through `wpml_object_id` with fallback to the original, no mandatory dependency, no URL manipulation. Verified: unit tests of the mapping, end-to-end with a mock (WPML's class name plus the filter) on the staging Playground, absence of hreflang on both Playgrounds. Requires a real WPML site: everything listed in `07-compatibility.md` §1. A first attempt at the mock defined WPML's version constant and crashed ACF's own WPML layer; detection now uses the `SitePress` class, which real WPML also provides.

## 7. WooCommerce compatibility (verified on the local site, WooCommerce 11.2.0)

| Page                              | Status          | Robots                                | Theme output                                                                  | Plugin output                                                                             |
| --------------------------------- | --------------- | ------------------------------------- | ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| Shop `/shop/` and `/shop/page/2/` | 200             | environment noindex                   | canonical (paginated), Open Graph, no description (empty shop page), no graph | none (the shipped shop template does not call the WooCommerce wrapper hooks)              |
| Simple product                    | 200             | environment noindex                   | core canonical, description from the short description, Open Graph, no graph  | WooCommerce `Product`                                                                     |
| Variable product                  | 200             | environment noindex                   | same                                                                          | WooCommerce `Product` with `AggregateOffer` (two variations)                              |
| Product category and its page 2   | 200             | environment noindex                   | canonical, Open Graph, no graph                                               | none                                                                                      |
| Cart, My account                  | 200             | noindex (WooCommerce and environment) | core canonical only                                                           | none                                                                                      |
| Checkout (empty cart)             | 302 to the cart | —                                     | —                                                                             | —                                                                                         |
| Simple product with Yoast active  | 200             | —                                     | nothing                                                                       | Yoast graph (WebPage, BreadcrumbList, WebSite) plus WooCommerce `Product`, no theme block |

No price, availability or customer data is read or written by the theme; the shop flows were not modified. The sample products, the category and the WooCommerce pages were removed afterwards and the plugin left installed but inactive. Report: `08-head-reports/local-woocommerce.md` (no duplicates).

## 8. Tests

| Command                                         | Result                                                                                                                                                                                                                                                              |
| ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `composer test:unit`                            | 83 passed (14 new: crawler groups and configuration, diagnostic rules for healthy, discouraged, undeclared and contradictory environments, provider states, crawler policy decisions, identity and page types, integrations; WPML mapping; WooCommerce allowances). |
| `npm run test:e2e` (CI mode, two Playgrounds)   | 55 passed, 3 skipped by design (mobile project). New: semantics on six routes including a JavaScript-disabled context, robots.txt with the custom groups after the core block and the sitemap line, WPML mock typing a mapped page, no hreflang from the theme.     |
| `npm run check -- --url http://localhost:10034` | 12/12.                                                                                                                                                                                                                                                              |
| `npm run test:scripts`                          | 5 passed.                                                                                                                                                                                                                                                           |
| Local WooCommerce matrix                        | Section 7; diagnostics with WooCommerce active: 13 PASS, 3 WARNING (no decision on crawlers, identity not configured, environment-related), 0 FAIL, 6 NOT VERIFIED.                                                                                                 |
| Head report on the local site                   | No duplicates, `html lang` it-IT, outline without skipped levels, no image without `alt`, no empty link.                                                                                                                                                            |

Not executed: the GitHub Actions run (no push authorised); WPML on a real installation; WooCommerce checkout with items, payments and account flows (outside the theme); Google, Bing and OpenAI access checks (not measurable from the theme).

## 9. Limitations

- The crawler policy governs the virtual robots.txt only; a physical file, the server, the CDN and user-initiated fetchers are outside it and reported as such.
- WPML compatibility is designed and mock-tested, not certified.
- WooCommerce was verified with sample data on the local site; the plugin's own structured data depends on the WooCommerce template hooks a site uses.
- The semantic checks guard the shipped templates and seeded content; client content (headings inside the editor, alternative texts) remains editorial.

## 10. Residual risks

- A site that never sets `crawlers.policy` keeps a permanent WARNING in the diagnostics by design, so the decision is visible.
- Vendors change crawler tokens; the configuration, not the theme, carries the list, and `05-crawler-policy.md` dates its matrix.
- CI has not run on the branch yet; all commands pass locally.

## 11. Git state

Branch `feature/seo-geo-foundation`: Phase 0 (`985a45b`), scan exclusion (`a96e2bd`), Increment A (`76a281d`, `2594dba`), Increment B (`961d598`, `9194e18`, `4efe0ed`, `5b1ac41`, `8eb8c71`), Increment C (`616a743`, `6c9532b`, `cae30dc`, `8592bfa` and the documentation commit). Working tree clean after the documentation commit. No push, merge, tag or deploy.

## 12. GO / NO-GO

| Criterion                                                                                 | Evidence                                                                                                        |
| ----------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| Crawler access configurable, never changed automatically, no vendor tokens in the runtime | `crawlers.*` keys, default `unset`, `Groups` validation, unit and e2e tests, residue scan green.                |
| robots.txt, indexability, headers, HTTP status, sitemaps, server-rendered content checked | Diagnostics checks with explicit NOT VERIFIED where the theme cannot measure; e2e and head report on real HTML. |
| Semantic HTML verified without template changes                                           | `06-content-semantics.md`, `semantics.spec.js`.                                                                 |
| WPML compatibility verifiable without a licence, not declared certified                   | Mock-tested; requirements listed; diagnostics NOT VERIFIED while active.                                        |
| WooCommerce compatibility verified on the local site                                      | Section 7 and the head report.                                                                                  |
| Diagnostics with PASS / WARNING / FAIL / NOT VERIFIED, no score, no external calls        | `Checks`, Site Health, WP-CLI exit code.                                                                        |
| No regressions from A and B                                                               | All previous tests pass; `npm run check` green.                                                                 |
| No new private data exposure                                                              | No new output on transactional pages; author and identity rules unchanged; head reports without personal data.  |
| Separate commits, clean tree, no push                                                     | Section 11.                                                                                                     |

Assessment: **GO** for Increment D, with the remote CI run and a real WPML installation still required before any release.
