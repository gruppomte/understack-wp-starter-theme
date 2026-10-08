# SEO/GEO Foundation — Configuration and Operations (Increment A)

Status: Increments A (Phases 1–3), B (structured data, organisation identity, breadcrumbs) and C (crawler policy, HTML semantics checks, WPML and WooCommerce compatibility, diagnostics with statuses) implemented on branch `feature/seo-geo-foundation`. Details: `04-structured-data.md`, `05-crawler-policy.md`, `06-content-semantics.md`, `07-compatibility.md`.

## 1. What the theme does

| Situation                                                                                                                                             | Behaviour                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| ----------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| No SEO plugin                                                                                                                                         | Core keeps the title, the singular canonical, the robots tag, the sitemap and `robots.txt`. The theme adds a meta description, canonicals for the front page, the posts index, archives and paginated listings, Open Graph and Twitter Card metadata, a JSON-LD graph (WebSite, the configured Organization, the page, articles, public authors, breadcrumbs), `noindex` on 404 and attachment pages, and removes the users list from the core sitemap. |
| Rank Math, Yoast SEO, SEOPress, All in One SEO, The SEO Framework or Slim SEO active                                                                  | The theme emits nothing for the signals the plugin owns: title, description, canonical, page-type robots directives, social metadata, structured data (including the BreadcrumbList node), sitemap, `robots.txt`. Rank Math and Yoast were measured (see `08-head-reports/`); the others are detected through their version constant and treated the same way, without certification.                                                                   |
| Plugin installed but inactive                                                                                                                         | No constant is defined, the theme behaves as if the plugin were absent.                                                                                                                                                                                                                                                                                                                                                                                 |
| `WP_ENVIRONMENT_TYPE` = `local`, `development` or `staging`                                                                                           | Every page served by WordPress gets `noindex, nofollow` as a meta directive and as an `X-Robots-Tag` header, with or without a plugin (through `wp_robots` for core and Yoast, through `rank_math/frontend/robots` for Rank Math). Canonicals, descriptions and social tags stay in place; `robots.txt` is untouched.                                                                                                                                   |
| `WP_ENVIRONMENT_TYPE` = `production` or not declared                                                                                                  | Indexable. An undeclared type is reported by the diagnostics.                                                                                                                                                                                                                                                                                                                                                                                           |
| Contradictory configuration (production type on a host outside `indexability.production_hosts`, or a non-production type on a listed production host) | Indexable, with an explicit warning in the diagnostics. The theme never guesses that a site is staging.                                                                                                                                                                                                                                                                                                                                                 |
| `blog_public` = 0                                                                                                                                     | Core's `noindex, nofollow` stays; the theme adds nothing.                                                                                                                                                                                                                                                                                                                                                                                               |

The theme never prints a robots tag of its own, never writes `robots.txt` rules to hide a site, never reads request headers to decide anything, and never changes server protections.

## 2. Configuration

Settings live in `config/seo.php` (an array of overrides, empty by default) and can be adjusted at runtime with the `<slug>/seo/config` filter, where `<slug>` is the theme slug (`Theme::SLUG`). Values are validated when loaded: an invalid value keeps its default and shows up in the diagnostics.

| Key                               | Default                 | Meaning                                                                                                                                                                      |
| --------------------------------- | ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `enabled`                         | `true`                  | Master switch for every theme-side SEO output, environment policy included.                                                                                                  |
| `provider`                        | `'auto'`                | `'auto'` detects the active plugin; `'none'` forces the fallbacks; a plugin id (`rank-math`, `yoast`, `seopress`, `aioseo`, `the-seo-framework`, `slim-seo`) forces silence. |
| `fallbacks.description`           | `true`                  | Meta description without a plugin.                                                                                                                                           |
| `fallbacks.canonical`             | `true`                  | Canonicals for non-singular pages without a plugin.                                                                                                                          |
| `fallbacks.social`                | `true`                  | Open Graph and Twitter Card without a plugin.                                                                                                                                |
| `with_provider`                   | `[]`                    | Signals to keep emitting while a plugin is active (`description`, `canonical`, `social`). You then own the duplicates.                                                       |
| `indexability.non_production`     | `'noindex'`             | `'noindex'` or `'off'`.                                                                                                                                                      |
| `indexability.production_hosts`   | `[]`                    | Optional explicit list of production host names (compared with the host of `home_url()`, exact match, lowercase; list both `www.` and bare forms when both serve the site).  |
| `indexability.x_robots_tag`       | `true`                  | Send the header on non-production copies.                                                                                                                                    |
| `indexability.not_found`          | `'noindex'`             | `'noindex'` or `'index'` for 404 pages.                                                                                                                                      |
| `indexability.attachments`        | `'noindex'`             | Same for attachment pages (only relevant when attachment pages are enabled).                                                                                                 |
| `indexability.post_types`         | `[]`                    | Post types to mark `noindex` (singular and archive) and drop from the core sitemap.                                                                                          |
| `indexability.taxonomies`         | `[]`                    | Taxonomies to mark `noindex` and drop from the core sitemap.                                                                                                                 |
| `sitemap.exclude_noindex`         | `true`                  | Apply the two lists above to the core sitemap.                                                                                                                               |
| `sitemap.users`                   | `false`                 | Keep the core users sitemap (author archives) in the index.                                                                                                                  |
| `social.twitter_card`             | `'summary_large_image'` | Card type when an image is available; `summary` is used without image.                                                                                                       |
| `social.twitter_site`             | `''`                    | Site handle for `twitter:site`, with or without `@`.                                                                                                                         |
| `social.image_size`               | `'wide'`                | Registered image size used for `og:image`. `wide` is 1600 px wide, uncropped; WordPress serves the original when the upload is smaller.                                      |
| `social.default_image`            | `0`                     | Attachment ID used when the page has no featured image.                                                                                                                      |
| `content.article_post_types`      | `['post']`              | Post types presented as articles (`og:type`, published/modified times, BlogPosting or Article nodes).                                                                        |
| `fallbacks.structured_data`       | `true`                  | JSON-LD graph without a plugin (`with_provider` may list `structured_data`).                                                                                                 |
| `structured_data.about_page`      | `0`                     | ID of the page typed as `AboutPage`; pages can also carry the "Structured data page type" field.                                                                             |
| `structured_data.contact_page`    | `0`                     | ID of the page typed as `ContactPage`.                                                                                                                                       |
| `structured_data.author_profiles` | `false`                 | `ProfilePage` on author archives and author archive URLs in Person nodes.                                                                                                    |
| `breadcrumbs.enabled`             | `false`                 | Breadcrumb trail in the page header and the BreadcrumbList node.                                                                                                             |
| `breadcrumbs.home_label`          | `''`                    | Label of the first item; the translatable "Home" when empty.                                                                                                                 |
| `crawlers.policy`                 | `'unset'`               | `'unset'` (no decision recorded, reported by the diagnostics), `'open'` (robots.txt untouched by choice) or `'custom'` (groups below appended).                              |
| `crawlers.groups`                 | `[]`                    | List of `['agents' => [...], 'allow' => [...], 'disallow' => [...], 'crawl_delay' => int]` appended to robots.txt; see `05-crawler-policy.md`.                               |

Example `config/seo.php` for a client site:

```php
<?php

declare(strict_types=1);

return [
    'indexability' => [
        'production_hosts' => ['www.example.com', 'example.com'],
    ],
    'social' => [
        'twitter_site' => '@brand',
        'default_image' => 123,
    ],
];
```

### Organisation identity

Site Settings → Organization (ACF): entity type, public name, legal name, description, logo, official website URL, public e-mail and telephone, address (street, city, region, postal code, two-letter country), areas served, official profiles. The social links of the General tab are added to `sameAs`. Everything is optional and validated on load; dropped values are listed by the diagnostics. See `04-structured-data.md` §3 for the rules (explicit type, LocalBusiness needs a full address).

### Breadcrumbs

Set `breadcrumbs.enabled` to `true` to render the trail at the top of the page header (pages, archives, single posts) and to add the BreadcrumbList node; `breadcrumbs.home_label` changes the first label. Pages with a hidden title and no lead keep no header at all, so they show no trail either.

## 3. Where each value comes from

- **Description** (max 160 characters, cut at a sentence or word boundary): manual excerpt → page lead (`page_lead` field) → beginning of the content; term description on term archives; biography on author archives; post type description on post type archives. Nothing on paginated pages, search, 404, password-protected, unpublished or preview content, and nothing on a front page that lists posts (set a static front page with an excerpt or a lead to describe it).
- **Canonical**: core on single posts, pages and attachments; theme on the front page, posts index, term, author, date and post type archives, with the `/page/N/` segment on paginated listings. Built from permalink functions, never from the request URL.
- **Social metadata**: `og:type` (`article` for article post types), `og:title` (document title), `og:description` (the description above), `og:url` (the canonical), `og:site_name`, `og:locale`, `og:image` with width, height and alt (featured image, else the default image), `article:published_time` and `article:modified_time` on articles; `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`, `twitter:site`. Nothing on search, 404 and previews.
- **Robots**: core's tag plus `noindex` (with `follow`) on 404, attachment pages and configured types; `noindex, nofollow` on non-production environments.

### Crawler policy

`crawlers.policy` records the owner's decision on crawlers, AI training crawlers included: `open` leaves robots.txt as WordPress (or the SEO plugin) serves it, `custom` appends the configured groups after the core block. Tokens and example policies are in `05-crawler-policy.md`; the theme ships none of them.

## 4. Diagnostics

- Site Health → Info → "SEO foundation": one field per check with its status (PASS, WARNING, FAIL, NOT VERIFIED) and a summary. Checks cover the environment and indexability, search engine visibility, the SEO plugin, signal ownership, robots directives and headers, robots.txt and the crawler policy, the sitemap, structured data and organisation identity, About/Contact page IDs, configuration validity, breadcrumbs, WPML and WooCommerce. NOT VERIFIED marks what the theme cannot measure: headers set by the server, robots.txt as served, user-initiated AI fetchers, rendered HTML, indexing status, CDN and firewall rules.
- WP-CLI: `wp <slug> seo report` prints the checks table, the measured facts and the summary, and exits with 1 when a check fails (usable in deployment scripts); `--format=json` returns everything, configuration included. No network request is made.
- `node tests/seo-report.mjs --url https://site --label "…"`: Markdown report of the real head output of a running site, with duplicate detection. Use it after activating or deactivating an SEO plugin and before go-live.

Warnings you may see: `WP_ENVIRONMENT_TYPE` not declared; host not in `production_hosts`; environment type contradicting the host list; `blog_public` = 0; provider forced to a plugin that is not active; Rank Math active but not yet registered or skipped (it prints nothing until then); a plugin detected but not verified; a physical `robots.txt` in the WordPress root; invalid configuration keys; organisation identity values dropped (unknown type, invalid URL, e-mail, telephone, country, small logo, LocalBusiness without a full address); no identity configured; About or Contact page IDs that are not published pages.

## 5. Staging → production checklist

1. Declare `WP_ENVIRONMENT_TYPE` on every copy (`wp-config.php` or the environment): `staging` on staging, `production` on production. Some hosts set it for you; check the diagnostics on each copy.
2. Protect staging at the server (HTTP authentication or IP allow-list) and send `X-Robots-Tag: noindex` for static files there. The theme covers only what WordPress serves.
3. Before go-live run `wp <slug> seo report` on production: no warnings, indexable, the expected plugin (or none) detected.
4. After activating or deactivating an SEO plugin, flush the permalinks (`wp rewrite flush` or Settings → Permalinks → Save): plugins replace the core sitemap rewrite rules and stale rules make `/wp-sitemap.xml` return 404.
5. Submit the right sitemap to Search Console and Bing Webmaster: `/wp-sitemap.xml` without a plugin, the plugin's index otherwise (`/sitemap_index.xml` for Rank Math and Yoast).
6. Run `node tests/seo-report.mjs --url https://www.example.com` and keep the output with the project.

## 6. Verifying a change

- `composer test:unit`: pure logic (configuration, provider detection, ownership, environment policy, robots merging, summaries, pagination URLs, identity validation, author privacy, graph assembly, serializer, breadcrumb trail).
- `npm run test:e2e`: two Playgrounds, production (defaults) and staging (plus `tests/playground/seo-features.php`, which enables breadcrumbs and author profiles), with the head and JSON-LD assertions in `tests/e2e/seo.spec.js` and `tests/e2e/seo-staging.spec.js`.
- `npm run check -- --url http://localhost:…`: the full suite including the SEO smoke checks against the local site.
- Provider matrix on the local site: activate one plugin at a time, flush permalinks, run the head report, deactivate, flush again. The results for Rank Math 1.0.280 and Yoast SEO 28.6 are in `08-head-reports/`.
