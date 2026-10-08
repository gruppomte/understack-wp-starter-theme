# SEO/GEO Foundation — Structured Data, Identity and Breadcrumbs (Increment B)

Status: implemented on branch `feature/seo-geo-foundation`. Configuration keys are listed in `03-configuration.md`; this document explains what the graph contains, where every value comes from and what it does not claim.

## 1. When the theme emits a graph

- Only when the theme owns the `structured_data` signal: no SEO plugin detected (or `with_provider` lists `structured_data`, which deliberately produces a second graph) and `fallbacks.structured_data` is on.
- Only on indexable page kinds: singular published content, the front page, the posts index, term, author, date and post type archives. Nothing on search results, 404 pages, previews, password-protected or unpublished content, and nothing on WooCommerce pages (WooCommerce prints its own WebSite, BreadcrumbList and Product nodes).
- The environment policy does not suppress the graph: a staging copy is `noindex` and still renders the same markup, so what you see on staging is what production will emit.
- One `<script type="application/ld+json">` per page, printed by the same head callback as the description and social tags, encoded with HTML-safe flags (no `<`, `>`, `&` or quotes survive unescaped) and pruned of empty values.

With Rank Math or Yoast active the theme prints nothing: both plugins emit their own graph (verified in `08-head-reports/`). The theme does not inject nodes into a plugin's graph; that integration is not supported.

## 2. Nodes

| Node                                                         | `@id`                               | Condition                                                                    | Properties (only when the data exists)                                                                                                                                                                                                                 |
| ------------------------------------------------------------ | ----------------------------------- | ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Organization or the configured subtype                       | `{home}#organization`               | Identity configured with a type and a name                                   | name, legalName (when different from the name), description, url (identity URL or the home), logo (ImageObject `{home}#logo` with url, width, height, caption) and image reference, email, telephone, address (PostalAddress), areaServed, sameAs      |
| WebSite                                                      | `{home}#website`                    | Always                                                                       | url, name (site title), description (tagline), publisher → organization, inLanguage                                                                                                                                                                    |
| ImageObject                                                  | `{canonical}#primaryimage`          | Singular content with a featured image                                       | url, contentUrl, width, height, caption (alt text), inLanguage                                                                                                                                                                                         |
| BreadcrumbList                                               | `{canonical}#breadcrumb`            | Breadcrumbs enabled and a trail of at least two items                        | itemListElement with position, name, item (the current page has no item)                                                                                                                                                                               |
| Person                                                       | `{home}#/schema/person/{opaque id}` | A public author (section 4) on an article, or the author of a profile page   | name, description (biography), url (author archive, only when `structured_data.author_profiles` is on), sameAs (the user's website when it is external to the site)                                                                                    |
| WebPage, CollectionPage, AboutPage, ContactPage, ProfilePage | `{canonical}#webpage`               | Every indexable page                                                         | url, name (document title), isPartOf → website, description (same as the meta description), inLanguage, datePublished and dateModified (singular), primaryImageOfPage, breadcrumb, about → organization (AboutPage), mainEntity → person (ProfilePage) |
| BlogPosting (post) or Article (other configured post types)  | `{canonical}#article`               | Published, not password-protected, post type in `content.article_post_types` | headline, description, url, mainEntityOfPage and isPartOf → webpage, datePublished, dateModified, author → person, publisher → organization, image → primaryimage, articleSection (categories), wordCount, inLanguage                                  |

Not produced, by design: FAQPage, Product, Offer, Review, AggregateRating, Event, VideoObject, Service, SearchAction (Google retired the sitelinks search box). Projects that need more add nodes through their own code; the serializer and the reference check are reusable.

Identifiers are stable: they derive from the home URL, the canonical URL of the page and, for people, an opaque hash of the user ID. Every reference (`{"@id": …}`) points to a node declared in the same graph; `Graph::danglingReferences()` verifies it and the tests assert it on real pages.

## 3. Organisation identity

Fields live on Site Settings → Organization (ACF options page, group `acf-json/group_site_settings.json`). All optional, nothing pre-filled. Values are read by `Seo\Identity\Repository` (through ACF, with a plain-option fallback) and validated by `Seo\Identity\Identity::fromArray()`, a plain value object that a companion plugin can feed through the `<slug>/seo/identity` filter without touching ACF.

| Field                                                                   | Property                | Validation                                                                                                                                                           |
| ----------------------------------------------------------------------- | ----------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Entity type (`org_type`)                                                | `@type`                 | One of the Schema.org types verified on 2026-10-08 (`Seo\Identity\Types`, extendable with `<slug>/seo/identity_types`). Unknown type: no Organization node, warning. |
| Public name (`org_name`)                                                | name                    | Plain text, 200 characters. Required for the node.                                                                                                                   |
| Legal name (`org_legal_name`)                                           | legalName               | Plain text, 200 characters; omitted when equal to the name.                                                                                                          |
| Description (`org_description`)                                         | description             | Plain text, 500 characters.                                                                                                                                          |
| Logo (`org_logo`)                                                       | logo, image             | Attachment; full-size URL, width and height. Below 112×112 px it is kept and reported (Google's minimum for the Organization logo).                                  |
| Official website URL (`org_url`)                                        | url                     | Absolute http(s) URL, else ignored with a warning; defaults to the home URL.                                                                                         |
| Public e-mail (`org_email`)                                             | email                   | RFC-valid address, else ignored with a warning.                                                                                                                      |
| Public telephone (`org_telephone`)                                      | telephone               | Digits, spaces, `+ ( ) . / -`, at least six digits.                                                                                                                  |
| Address (`org_address`: street, locality, region, postal code, country) | address (PostalAddress) | Plain text; country as a two-letter ISO code (upper-cased).                                                                                                          |
| Areas served (`org_areas_served`)                                       | areaServed              | Plain text list, unique, up to 50.                                                                                                                                   |
| Official profiles (`org_same_as`) + social links from the General tab   | sameAs                  | Absolute http(s) URLs, unique; invalid entries are reported.                                                                                                         |

Rules:

- The type is always explicit. An address never turns an Organization into a LocalBusiness.
- A LocalBusiness type (or any of its descendants) without street, locality and country is emitted as `Organization` and reported, because an incomplete LocalBusiness is worse than a correct Organization.
- Without a name there is no Organization node, no publisher and no `about` reference.
- The diagnostics (Site Health → Info, `wp <slug> seo report`) list every dropped value and the effective type.

## 4. Authors and privacy

A Person node appears only for an author whose display name is a public, trustworthy name:

- empty, one-character or symbol-only names are rejected;
- names containing `@`, `<`, `>`, `/`, `\` or a URL are rejected (an e-mail address is never turned into a name);
- a display name equal to the login, the nicename, the account e-mail or its local part is rejected;
- nothing else from the account is used: no e-mail, no login, no nicename, no Gravatar.

When the author is rejected the article simply has no `author` property; the theme never invents an Organization author. The author archive URL appears in the Person node only when `structured_data.author_profiles` is on, and the user's website only when it is external to the site. Dates come from the post (`get_post_datetime`, ISO 8601 with offset) and are never generated.

## 5. Page types

`WebPage` is the default for singular content, `CollectionPage` for listings. `AboutPage` and `ContactPage` are assigned only when told so, in this order:

1. the page's own "Structured data page type" field (editorial override, stored as `page_schema_type`), which survives translation because each translated page carries its own value;
2. the page ID in `structured_data.about_page` / `structured_data.contact_page`, passed through the `<slug>/seo/page_id` filter so a multilingual plugin can map it to the current language.

No detection by slug, title or template. `ProfilePage` is used on author archives when `structured_data.author_profiles` is on and the author qualifies under section 4; otherwise the archive is a `CollectionPage`.

## 6. Breadcrumbs

`breadcrumbs.enabled` (default off) switches on both the visible trail and the BreadcrumbList node. The trail is built from the site structure, never from the URL: Home → posts page (when set) → category and its parents, or page ancestors, or the post type archive, or parent terms, or year/month/day → current item → "Page N" on paginated listings. Unpublished ancestors and terms without a valid link are skipped; search, 404 and the front page have no trail; an author whose name is not public is labelled "Author".

Rendering: `views/partials/breadcrumbs.twig` (`nav` with `aria-label`, an ordered list, `aria-current="page"` on the last item, theme typography tokens) included at the top of `sections/page-header.twig` and of the single post header. When disabled the Twig function `breadcrumbs()` returns an empty list and the markup is byte-for-byte the previous one (asserted by the end-to-end suite).

Ownership: the node follows the graph owner (section 1). With a plugin active the theme's visible trail can still be enabled; the plugin's BreadcrumbList is then the structured data, and the project should keep the two consistent or disable one of them.

## 7. A real graph

Captured on the local site with a sample identity (host replaced by `https://www.example.com`, site name by "Example site"):

```json
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "ProfessionalService",
      "@id": "https://www.example.com/#organization",
      "name": "Studio Esempio",
      "legalName": "Studio Esempio S.r.l.",
      "description": "Sample organisation used to document the structured data output.",
      "url": "https://www.example.com/",
      "email": "info@example.com",
      "telephone": "+39 02 0000 0000",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Via Esempio 1",
        "addressLocality": "Milano",
        "addressRegion": "MI",
        "postalCode": "20100",
        "addressCountry": "IT"
      },
      "areaServed": ["Milano", "Lombardia"],
      "sameAs": ["https://www.wikidata.org/wiki/Q42"]
    },
    {
      "@type": "WebSite",
      "@id": "https://www.example.com/#website",
      "url": "https://www.example.com/",
      "name": "Example site",
      "publisher": {
        "@id": "https://www.example.com/#organization"
      },
      "inLanguage": "it-IT"
    },
    {
      "@type": "Person",
      "@id": "https://www.example.com/#/schema/person/d6195c43e258",
      "name": "Amministratore",
      "sameAs": ["https://www.example.com"]
    },
    {
      "@type": "WebPage",
      "@id": "https://www.example.com/ciao-mondo/#webpage",
      "url": "https://www.example.com/ciao-mondo/",
      "name": "Ciao mondo! – Example site",
      "isPartOf": {
        "@id": "https://www.example.com/#website"
      },
      "description": "Benvenuto in WordPress. Questo è il tuo primo articolo. Modificalo o cancellalo e inizia a scrivere!",
      "inLanguage": "it-IT",
      "datePublished": "2026-09-03T14:59:09+02:00",
      "dateModified": "2026-09-03T14:59:09+02:00"
    },
    {
      "@type": "BlogPosting",
      "@id": "https://www.example.com/ciao-mondo/#article",
      "headline": "Ciao mondo!",
      "description": "Benvenuto in WordPress. Questo è il tuo primo articolo. Modificalo o cancellalo e inizia a scrivere!",
      "url": "https://www.example.com/ciao-mondo/",
      "mainEntityOfPage": {
        "@id": "https://www.example.com/ciao-mondo/#webpage"
      },
      "isPartOf": {
        "@id": "https://www.example.com/ciao-mondo/#webpage"
      },
      "datePublished": "2026-09-03T14:59:09+02:00",
      "dateModified": "2026-09-03T14:59:09+02:00",
      "author": {
        "@id": "https://www.example.com/#/schema/person/d6195c43e258"
      },
      "publisher": {
        "@id": "https://www.example.com/#organization"
      },
      "articleSection": ["Senza categoria"],
      "wordCount": 16,
      "inLanguage": "it-IT"
    }
  ]
}
```

## 8. What valid structured data does not guarantee

Schema.org validity and Google eligibility are different things. Google's documentation at the time of writing: the Organization logo must be at least 112×112 px on a crawlable URL; the Article rich result wants an image and is not guaranteed; BreadcrumbList should reflect a visible trail; LocalBusiness requires a postal address; FAQ rich results were retired in 2026; the sitelinks search box was retired in 2024. The theme produces correct, verifiable markup from real data and nothing more. Before go-live validate a public URL with Google's Rich Results Test and the Schema Markup Validator and record the result; the project never claims eligibility on its own.

## 9. Verification

- Unit: `composer test:unit` (identity validation, type list, author privacy, graph assembly and references, serializer, breadcrumb trail).
- End-to-end: `npm run test:e2e` asserts the graph on real pages of the production Playground (organisation with logo, website, collection page, article with and without a person, About and Contact pages, no graph on search and 404, breadcrumbs absent) and of the staging Playground with `tests/playground/seo-features.php` (breadcrumb trail and BreadcrumbList, paginated listings, author profile pages).
- Local matrix: `08-head-reports/` shows the theme graph without a plugin and the plugin graphs with Rank Math and Yoast, with no duplicate blocks.
