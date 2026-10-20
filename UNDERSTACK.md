# Working on this theme

Conventions for coding agents and developers. README.md describes the architecture; this file lists what must not regress.

## Definition of done

A task is complete only when the relevant checks pass:

```bash
npm run lint
npm run analyse
npm run build
npm run check -- --url http://<local-site>
npm run test:e2e          # when templates, JavaScript, navigation or SEO output changed
composer test:unit        # when app/Seo or other pure PHP logic changed
```

Run them; do not assume. If a command is missing for a new kind of check, add it to `package.json` or `composer.json` instead of inventing one in a report.

## Architecture rules

- `functions.php` stays a bootstrap. Hooks, post types, ACF, assets and helpers live in classes under `app/`, registered through `Core\Theme::SERVICES`.
- PHP prepares data, Twig renders it. No queries, business logic or data transformation in templates; put them in a controller, a service or `Support\Posts`.
- Business functionality that must survive a theme switch (domain post types, integrations, leads, webhooks, cron, REST endpoints) goes in a site plugin, not in the theme.
- Blocks follow `blocks/<name>/` (block.json, render.php, block.twig, optional style.css, editor.css, view.js) and their fields live in `acf-json/`. Never rename a block, an attribute or a field key once content uses it.
- Design tokens come from `theme.json`; Tailwind consumes them in `src/css/theme.css` (`@theme inline`). Do not add parallel colour, font or size tokens.
- Prefer WordPress core APIs to new dependencies. Any new package needs a reason that a browser API or core cannot cover.
- No page builders, no jQuery in theme code, no admin branding, no generator or agency credits anywhere in the output.
- SEO output goes through `app/Seo` only: one `wp_head` callback, the `wp_robots` and `rank_math/frontend/robots` filters, `wp_headers`, the sitemap filters. Templates never print meta tags, canonicals or JSON-LD. A signal is emitted only when `Seo\Ownership` says the theme owns it; an active SEO plugin owns everything it supports. Per-site settings live in `config/seo.php`, never hardcoded; nothing site-specific or agency-specific is pre-filled.
- Indexability is explicit: `WP_ENVIRONMENT_TYPE` and `indexability.production_hosts` decide, never a domain pattern. Never add `noindex` to a declared production site, never use `robots.txt` to hide a staging copy, never touch server protections from the theme.
- Crawler access is the site owner's decision: no user-agent token or vendor URL in the runtime, only `crawlers.groups` from the configuration appended to the virtual `robots.txt`. Diagnostics stay read-only and offline, with NOT VERIFIED for anything the theme cannot measure; no scores. Product, Offer and hreflang belong to WooCommerce, WPML or the SEO plugin, never to the theme.

## Output and security

- Escape at output: `|e`, `|esc_attr`, `|esc_url`, `|wp_kses_post`. Twig autoescape is off on purpose (see README); `|raw` is not used.
- Sanitise ACF and user data in PHP before it reaches a template.
- Every state-changing endpoint: nonce and capability check, validated and sanitised input, real `permission_callback` for REST routes.
- Never commit secrets; `ACF_PRO_LICENSE` lives in `wp-config.php`.

## Performance

- Load JavaScript on demand through the feature list in `src/js/app.js`; GSAP and Alpine are never imported globally.
- Keep the LCP element out of entrance animations; never hide content with CSS until JavaScript runs.
- Use `components/image.twig` for attachments (`priority: true` for the LCP image, `loading: 'eager'` above the fold).
- Prime caches for listings (`Support\Posts::prime`) and read terms with `post_terms()` in Twig.

## Accessibility

Semantic landmarks, one `h1`, continuous heading order, visible focus, native `dialog`/`details` before custom widgets, `aria-*` only when HTML cannot express the state, `prefers-reduced-motion` respected, 3:1 contrast for control boundaries, 4.5:1 for text.

## Local sites and credentials

- The login slug (`_admin` by default) is a URL served by `site/mu-plugins/login-slug.php` through the `WP_LOGIN_SLUG` constant. It is never a username; keep it configured in one place (`.env.local` → `npm run local:setup` → `wp-config.php`).
- The development administrator keeps its username; `npm run local:setup` only sets its email and, on the first run, the local password from `.env.local`. Never add code that sets or resets passwords at runtime, and never commit `.env.local` or any password.

## White-label

The theme is delivered to clients: no reference to the original base, to tools or to the people who built it may appear in code, comments, metadata or the rendered HTML. `npm run scan` enforces it; `npm run setup` initialises a client project. Third-party licence notices under `includes/acf` and `vendor` are never modified.
