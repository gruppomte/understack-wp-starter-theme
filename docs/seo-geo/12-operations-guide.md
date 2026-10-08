# SEO/GEO Foundation — Operations Guide for Client Projects

For the agency team starting a white-label project from this starter. It says what the theme does on its own, what needs the client's data or decision, and what stays outside the theme. Reference documents: `03-configuration.md` (keys), `04-structured-data.md` (graph), `05-crawler-policy.md` (crawlers), `07-compatibility.md` (WPML, WooCommerce).

## 1. What is automatic and what is not

| Automatic (no configuration)                                                                                                                                                                                                                                                                                                                                              | Needs client data or a decision                                                                                                                                                                                                                     | Outside the theme                                                                                                                                                                                                                                                                 |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Meta description from excerpt, page lead or content; canonicals on listings; Open Graph and Twitter Card; `noindex` on 404 and attachment pages; core sitemap without the users list; one JSON-LD graph (WebSite, page, articles, public authors); silence when an SEO plugin is active; `noindex, nofollow` plus `X-Robots-Tag` on local, development and staging copies | Organisation identity (Site Settings → Organization); About and Contact pages; breadcrumbs on or off; crawler decision (`crawlers.policy`); production host list; default social image and Twitter handle; article post types; choice of SEO plugin | Search Console and Bing Webmaster registration; `WP_ENVIRONMENT_TYPE` on every copy; staging access control; CDN, WAF and web server rules; a physical `robots.txt`; HTTPS and host canonicalisation; WPML and WooCommerce settings; rankings and AI citations (never guaranteed) |

## 2. Initial SEO/GEO setup (per client)

1. Declare `WP_ENVIRONMENT_TYPE` in `wp-config.php` of every copy: `production` on the live site, `staging` or `development` elsewhere. Without it the site is treated as production and the diagnostics warn.
2. Decide the SEO plugin. With none, the theme fallbacks are active. With Rank Math or Yoast, complete the plugin setup (Rank Math prints nothing until its registration is done or skipped), then flush permalinks.
3. Fill Site Settings → Organization: entity type (explicit; choose a LocalBusiness type only for a business visited at a physical address, and then fill street, city and country), public name, legal name, description, logo (at least 112×112 px, PNG/JPG/WebP), official URL if different, public e-mail and telephone, address, areas served, official profiles. The social links of the General tab join `sameAs`.
4. Mark the About and Contact pages with the "Structured data page type" field (or set their IDs in `config/seo.php`).
5. Edit `config/seo.php`: `indexability.production_hosts` (recommended), `crawlers.policy` (`open` or `custom`, see section 7), `breadcrumbs.enabled` if the design wants a trail, `social.default_image`, `social.twitter_site`, extra `content.article_post_types` when a custom post type is editorial.
6. Run the diagnostics (section 11) on staging and on production; resolve every WARNING or FAIL you can.
7. Validate a public URL with Google's Rich Results Test and the Schema Markup Validator; keep the result with the project.
8. Submit the sitemap to Search Console and Bing Webmaster Tools: `/wp-sitemap.xml` without a plugin, the plugin's index otherwise.

## 3. Client configuration checklist

- [ ] `WP_ENVIRONMENT_TYPE` declared on production, staging, local.
- [ ] Staging behind HTTP authentication or an allow-list; `X-Robots-Tag` for static files at the server.
- [ ] SEO plugin decided; if present, set up and verified with the head report.
- [ ] Organization tab filled; diagnostics show no identity warning.
- [ ] About and Contact pages typed.
- [ ] `config/seo.php` reviewed: production hosts, crawler policy, breadcrumbs, social defaults.
- [ ] Permalinks flushed after any plugin activation or deactivation.
- [ ] Head report and diagnostics archived before go-live; Rich Results Test on a public URL.
- [ ] Sitemap submitted; `robots.txt` fetched from outside and compared with the expected content.

## 4. The Organization tab (ACF)

Every field is optional and validated when read: invalid URLs, e-mails, telephone numbers and country codes are dropped and reported, HTML is stripped, lengths are bounded. Rules that matter:

- The type is never inferred. `Organization` fits most clients; subtypes (`Corporation`, `NGO`, `EducationalOrganization`, …) and LocalBusiness types (`Restaurant`, `Dentist`, `Store`, …) are listed in the field.
- A LocalBusiness type without street, city and country is emitted as `Organization` with a warning.
- Without a public name there is no Organization node and no publisher.
- The logo is served at its uploaded size; keep it reasonably light and at least 112×112 px.

## 5. JSON-LD

One graph per page: Organization (when configured), WebSite, the page (WebPage, CollectionPage, AboutPage, ContactPage, ProfilePage), BlogPosting/Article for article post types, ImageObject for the featured image, BreadcrumbList when breadcrumbs are enabled, Person for authors with a public display name. Identifiers are stable and every reference points to a node of the same graph. Not produced: FAQPage, Product, Offer, Review, AggregateRating, Event, VideoObject, SearchAction. Validity does not mean eligibility for rich results: see `04-structured-data.md` §8.

## 6. SEO plugins

| Situation                                             | What happens                                                                                                                                                                                                                                                                       |
| ----------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| No plugin                                             | Theme fallbacks for description, canonicals of listings, social tags, JSON-LD, 404/attachment robots, sitemap filters.                                                                                                                                                             |
| Rank Math or Yoast                                    | The plugin owns title, description, canonical, robots directives, social tags, JSON-LD, sitemap and `robots.txt` body; the theme keeps only the environment policy (through the plugin's own channel) and the crawler groups appended to `robots.txt`. Measured with both plugins. |
| SEOPress, All in One SEO, The SEO Framework, Slim SEO | Detected by their version constant and treated like the two above, but not measured: run the head report and look for duplicates before trusting the combination.                                                                                                                  |
| Plugin installed but inactive                         | Ignored.                                                                                                                                                                                                                                                                           |
| Plugin active with a feature switched off             | The theme stays silent for that signal unless `with_provider` lists it, in which case you own the duplicates.                                                                                                                                                                      |

Rank Math note: after activation complete or skip the registration, otherwise the front end prints nothing. Both plugins: flush permalinks after activation or deactivation or `/wp-sitemap.xml` answers 404.

## 7. Crawlers

`crawlers.policy` records the client's decision: `open` (allow everything, including AI training crawlers), or `custom` with groups appended to `robots.txt`. The theme never decides for the client and ships no user-agent list; the verified tokens, the difference between search, training and user-initiated fetching, and three example policies are in `05-crawler-policy.md`. User-initiated fetchers are not governed by `robots.txt`; only server-side access control is. Check the CDN or WAF bot rules, which can contradict `robots.txt`.

## 8. Production and staging

The theme makes every `local`, `development` or `staging` copy `noindex, nofollow` (meta directive and `X-Robots-Tag` header), also with Rank Math or Yoast. It never guesses from the domain, never adds `noindex` to a declared production site, never uses `robots.txt` to hide a copy, and reports contradictions (production type on a host outside `indexability.production_hosts`, or the opposite) instead of acting on them. Real protection of staging stays at the server. Canonicals and sitemap URLs always follow `home_url()`, so a copy with its own home URL never points to production.

Going live: set `WP_ENVIRONMENT_TYPE=production`, run `wp <slug> seo report` (expect no FAIL), fetch `/robots.txt` and a few pages from outside, run the head report, submit the sitemap.

## 9. WooCommerce

Verified with WooCommerce 11.2: Product and Offer data stay with WooCommerce (or the SEO plugin); the theme emits no graph and no breadcrumbs on shop pages and nothing on cart, checkout and account pages; it adds canonicals and social tags on the shop, categories and paginated listings and the product short description as meta description. No price, stock or customer data is read. Details and limits: `07-compatibility.md` §2.

## 10. WPML

Compatibility is designed and mock-tested, not certified: no hreflang from the theme, language-aware URLs and identifiers, breadcrumbs from translated objects, About/Contact IDs mapped to the current language, no mandatory dependency. Before relying on it, test on a real WPML site the items listed in `07-compatibility.md` §1 and keep the diagnostics' NOT VERIFIED in mind.

## 11. Diagnostics

- Site Health → Info → "SEO foundation": one field per check with PASS, WARNING, FAIL or NOT VERIFIED and a summary.
- `wp <slug> seo report` (replace `<slug>` with the theme slug, `understack-wp-starter` in this repository): checks, measured facts and summary; exit code 1 when a check fails, usable in deployment scripts. `--format=json` for automation. No network request is made.
- `node tests/seo-report.mjs --url https://www.example.com --label "go-live"`: Markdown head report of a running site (titles, descriptions, canonicals, robots, social tags, JSON-LD types, `html lang`, heading outline, images without alt, empty links, duplicates). From the project repository, Node 22+.

NOT VERIFIED is never a pass: server headers, `robots.txt` as served, user-initiated AI fetchers, indexing status and CDN rules need their own tools.

## 12. Tests before publishing

From the project repository: `composer test:unit`, `npm run test:e2e` (two Playgrounds), `npm run check -- --url <local site>`, `npm run release` (the ZIP must contain runtime files only). On the live site: diagnostics, head report, Rich Results Test, external `robots.txt` and sitemap fetch.

## 13. Troubleshooting

| Symptom                                        | Cause and fix                                                                                                                                                         |
| ---------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| No description, canonical or JSON-LD on a page | An SEO plugin is active (expected), or the page is search/404/preview/protected, or `config/seo.php` disables the fallback. Check `wp <slug> seo report` → ownership. |
| Two canonicals or two graphs                   | `with_provider` lists a signal while a plugin is active, or an unmeasured plugin. Remove the override or check the head report.                                       |
| Staging indexable                              | `WP_ENVIRONMENT_TYPE` missing or `production`; a host listed in `production_hosts` while the type is staging. Fix the declaration.                                    |
| Production `noindex`                           | `WP_ENVIRONMENT_TYPE` set to staging/local on production, or Settings → Reading discourages search engines (FAIL in the diagnostics).                                 |
| `/wp-sitemap.xml` is 404                       | Stale rewrite rules after a plugin change: flush permalinks. A plugin may own the sitemap (`/sitemap_index.xml`).                                                     |
| Crawler groups not in `robots.txt`             | A physical `robots.txt` exists; or `crawlers.policy` is not `custom`; or a group is invalid (FAIL in the diagnostics).                                                |
| No Organization node                           | Identity not configured, or no name, or an unknown type; see the identity warnings.                                                                                   |
| Author missing from an article                 | The display name is a login, an e-mail or otherwise not public; give the user a real display name.                                                                    |
| Rank Math active but no SEO tags               | Registration not completed or skipped.                                                                                                                                |

## 14. Limits of the GEO features

The theme gives generative engines the same things it gives classic search: server-rendered semantic HTML, one heading structure, visible authors and dates, verifiable structured data from real client data, a documented crawler policy. It does not score "AI readiness", does not generate FAQs or summaries, does not add `llms.txt` or Markdown mirrors, and does not promise citations in assistants or rankings in Google. Those depend on the content, the authority of the site and the vendors' own systems.

## 15. Moving to a companion plugin later

The data model is plain PHP: `Seo\Identity\Identity::fromArray()` takes an array, `Seo\Config` takes an array, the graph builder takes an `Input` object. A plugin that wants to own the identity provides it through the `<slug>/seo/identity` filter (and types through `<slug>/seo/identity_types`); configuration can be injected through `<slug>/seo/config`; page mapping through `<slug>/seo/page_id`. Nothing in the theme stores state of its own, so moving the ACF fields to a plugin only means changing the reader, not the data.
