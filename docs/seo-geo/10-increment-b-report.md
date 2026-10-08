# SEO/GEO Foundation — Increment B report (structured data, identity, breadcrumbs)

Date: 2026-10-08 · Branch: `feature/seo-geo-foundation` · Commits of this increment: `961d598` (ACF field groups), `9194e18` (implementation), `4efe0ed` (tests and fixtures), plus the documentation commit that contains this report. Nothing was pushed, merged, tagged or deployed; the remote CI run is still pending for the whole branch.

## 1. Architecture

| Component                                             | Responsibility                                                                                                                                                                                               |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `Seo\Identity\Identity`                               | Plain value object of the organisation: validation and sanitisation of every field, explicit type, LocalBusiness-without-address fallback to Organization, issues for the diagnostics. Independent from ACF. |
| `Seo\Identity\Types`                                  | Schema.org Organization and LocalBusiness types verified on schema.org on 2026-10-08 (176 types); extendable through `<slug>/seo/identity_types`.                                                            |
| `Seo\Identity\Repository`                             | Reads the ACF option fields (with a plain-option fallback), merges the social links into `sameAs`, applies `<slug>/seo/identity` so a companion plugin can replace the source without migrations.            |
| `Seo\StructuredData\Input`                            | Plain data collected once per request (or built directly in tests).                                                                                                                                          |
| `Seo\StructuredData\Collector`                        | Gathers the input from WordPress: canonical, page type (editorial field → configured IDs through `<slug>/seo/page_id` → listing kind), article data, author, featured image, breadcrumbs, identity.          |
| `Seo\StructuredData\Author`                           | Privacy rules for authors: public display names only, nothing derived from logins or e-mails.                                                                                                                |
| `Seo\StructuredData\Graph`                            | Pure, deterministic graph builder with stable `@id`s, node links and a reference-integrity check.                                                                                                            |
| `Seo\StructuredData\Serializer`                       | Pruning of empty values and HTML-safe JSON encoding; one `application/ld+json` script printed by `Meta\Head` through `wp_print_inline_script_tag()`.                                                         |
| `Seo\Breadcrumbs` + `views/partials/breadcrumbs.twig` | Trail from the site structure, exposed to Twig as `breadcrumbs()` and included in the page header and the single post header; renders nothing unless enabled.                                                |
| `Seo\Config`, `Signal`, `Ownership`                   | New keys (`fallbacks.structured_data`, `structured_data.*`, `breadcrumbs.*`) and the `structured_data` and `breadcrumbs` signals resolved against the provider.                                              |
| `Seo\Diagnostics`                                     | Identity state, dropped values, structured data owner, page ID checks, breadcrumbs state in Site Health and `wp <slug> seo report`.                                                                          |

Details in `04-structured-data.md`; configuration in `03-configuration.md`.

## 2. Files

Created: `app/Seo/Identity/{Identity,Types,Repository}.php`, `app/Seo/StructuredData/{Input,Graph,Serializer,Collector,Author}.php`, `app/Seo/Breadcrumbs.php`, `views/partials/breadcrumbs.twig`, `tests/unit/{IdentityTest,GraphTest,AuthorTest,SerializerTest,BreadcrumbsTest}.php`, `tests/playground/seo-features.php`, `docs/seo-geo/04-structured-data.md`, this report.

Modified: `acf-json/group_site_settings.json` (General and Organization tabs), `acf-json/group_page_header.json` (page type field), `app/Seo/{Config,Signal,Ownership,Seo}.php`, `app/Seo/Meta/{Head,Social}.php`, `app/Seo/Diagnostics/{Report,SiteHealth}.php`, `views/sections/page-header.twig`, `views/singles/single.twig`, `config/seo.php`, `languages/*.pot`, `tests/unit/OwnershipTest.php`, `tests/e2e/{seo-helpers,seo.spec,seo-staging.spec}.js`, `tests/playground/seed.php`, `tests/smoke.php`, `scripts/playground.mjs` (`--fixture`), `playwright.config.js`, `docs/seo-geo/{01,02,03}-*.md`, `README.md`.

Untouched: styles, scripts, blocks, controllers, other templates.

## 3. ACF fields

Site Settings → Organization tab (all optional, no defaults): `org_type` (select of verified Schema.org types), `org_name`, `org_legal_name`, `org_description`, `org_logo` (image, PNG/JPG/WebP), `org_url`, `org_email`, `org_telephone`, `org_address` (group: `street`, `locality`, `region`, `postal_code`, `country`), `org_areas_served` (repeater: `area`), `org_same_as` (repeater: `url`). The existing `social_links` feed `sameAs` as well. A "General" tab now wraps the previous fields; no key or name changed.

Page header group: `page_schema_type` (select: regular page, About page, Contact page), the editorial override for the page type.

The database copies of the groups on the local site were synchronised with `wp eval-file scripts/acf-json.php sync`.

## 4. Real graph examples

A single post on the local site with a sample identity (host replaced) is reproduced in `04-structured-data.md` §7: ProfessionalService with address, areas and `sameAs`; WebSite with publisher; Person with a public name only; WebPage; BlogPosting linked to the page, the person and the organisation.

The staging Playground with breadcrumbs enabled, page `/about/team/` (values asserted by the suite):

```json
{
  "@type": "BreadcrumbList",
  "@id": "http://127.0.0.1:9401/about/team/#breadcrumb",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "http://127.0.0.1:9401/" },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "About us",
      "item": "http://127.0.0.1:9401/about/"
    },
    { "@type": "ListItem", "position": 3, "name": "Team" }
  ]
}
```

The production Playground emits for the organisation seeded by `tests/playground/seed.php` a logo ImageObject generated at seed time (300×300 PNG), the address, two areas served and two `sameAs` URLs; the post by the administrator has no Person (display name equal to the login), the post by "Alice Rossi" has one without archive URL, e-mail or login.

## 5. Provider matrix (local site, `WP_ENVIRONMENT_TYPE=local`, sample identity configured during the capture)

| State             | Theme graph                                                | Plugin graph                                                | Duplicates | Environment policy                            |
| ----------------- | ---------------------------------------------------------- | ----------------------------------------------------------- | ---------- | --------------------------------------------- |
| No plugin         | yes (Organization subtype, WebSite, page, article, person) | —                                                           | none       | noindex meta + header                         |
| Rank Math 1.0.280 | none                                                       | yes (own Organization, WebSite, WebPage, Article, Person)   | none       | preserved through `rank_math/frontend/robots` |
| Yoast SEO 28.6    | none                                                       | yes (own WebSite, WebPage, Article, Person, BreadcrumbList) | none       | preserved through `wp_robots`                 |

Reports: `08-head-reports/local-core-only.md`, `local-rank-math.md`, `local-yoast.md`. The sample identity was removed from the local site afterwards; both plugins are inactive again and the permalinks were flushed.

## 6. Tests

| Command                                         | Result                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| ----------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `composer test:unit`                            | 69 passed: Organization complete, partial and absent; LocalBusiness valid and insufficient; ACF value sanitisation; sameAs and logo valid and invalid; author privacy and e-mail exclusion; About and Contact pages; no duplicate nodes; `@id` integrity; serializer escaping; breadcrumb trail; ownership of the new signals; all Increment A tests.                                                                                                             |
| `npm run test:e2e` (CI mode, two Playgrounds)   | 42 passed, 3 skipped by design. Production: graph on home, posts (with and without person), About, Contact, nested page; logo; no graph on search and 404; no breadcrumb trail and unchanged header with the feature off. Staging: breadcrumb trail and BreadcrumbList on a nested page, a post, a paginated category; no trail on the front page; profile pages only for public names; author archive URL when profiles are enabled; all Increment A assertions. |
| `npm run check -- --url http://localhost:10034` | 12/12 (syntax, Composer, autoloader, manifest, ESLint, Prettier, PHPCS, PHPStan, residue scan, tooling tests, WordPress smoke test with identity and graph checks, HTTP smoke test).                                                                                                                                                                                                                                                                              |
| `npm run test:scripts`                          | 5 passed.                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| Provider matrix                                 | section 5.                                                                                                                                                                                                                                                                                                                                                                                                                                                        |

Not executed: the GitHub Actions run (no push authorised), Google Rich Results Test and Schema Markup Validator on a public URL (manual go-live step, see `04-structured-data.md` §8), WPML and WooCommerce (Increment D).

## 7. Differences from the approved plan

- No geo coordinates, opening hours or price range fields: not in the approved field list.
- `SearchAction` is not emitted (sitelinks search box retired by Google).
- Breadcrumbs reach the templates through a `breadcrumbs()` Twig function rather than the context, because the page header is included with `only`.
- A profile page without a public author falls back to `CollectionPage`.
- The staging Playground doubles as the "features on" fixture (breadcrumbs, author profiles) through `--fixture`, keeping CI at two Playgrounds.
- The user's website field is ignored when it points to the site itself (WordPress default for the first user), so `sameAs` only lists external profiles.

## 8. Residual risks

- Rich result eligibility is not claimed: validate public URLs with Google's tools before go-live.
- Providers other than Rank Math and Yoast are detected but not measured; with them the theme is silent as designed.
- The logo is read at full size; very large logos are served as uploaded (no resizing).
- With a plugin active and the theme's visible breadcrumbs enabled, the plugin's BreadcrumbList is the structured data; keep both consistent or disable one.
- CI has not run on the branch yet; all commands pass locally.

## 9. Git state

Branch `feature/seo-geo-foundation`: Phase 0 (`985a45b`), scan exclusion (`a96e2bd`), Increment A (`76a281d`, `2594dba`), Increment B (`961d598`, `9194e18`, `4efe0ed` and the documentation commit). Working tree clean after the documentation commit. No push, merge, tag or deploy.

## 10. GO / NO-GO

| Criterion                                                          | Evidence                                                                                                        |
| ------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------- |
| Identity fields optional, validated, no agency data                | `Identity::fromArray()` rules, unit tests, empty defaults in `acf-json` and `config/seo.php`.                   |
| Explicit entity type, no LocalBusiness from an address             | Types list, fallback to Organization with a warning, unit test.                                                 |
| Data model independent from ACF                                    | Value object + `<slug>/seo/identity` filter, plain-option fallback.                                             |
| Author privacy                                                     | `Author` rules, 5 unit tests, e2e on the administrator and on "Alice Rossi", no e-mail or login in any payload. |
| AboutPage / ContactPage by explicit configuration only             | Editorial field and configured IDs, filter for multilingual mapping, e2e.                                       |
| Coherent, deterministic graph with stable `@id`s and no duplicates | Unit reference check, e2e on real pages, head reports.                                                          |
| No second graph with a provider                                    | Provider matrix, ownership tests.                                                                               |
| Breadcrumbs behind a flag, no layout change when off               | e2e asserts the absence of the nav and the unchanged header; byte-identical markup when disabled.               |
| No regressions from Increment A                                    | All previous unit and e2e tests pass; `npm run check` green.                                                    |
| Documentation                                                      | `04-structured-data.md`, updated `01`, `02`, `03`, README, CLAUDE.md, head reports.                             |

Assessment: **GO** for the next increment, with the remote CI run still to be executed before any release.
