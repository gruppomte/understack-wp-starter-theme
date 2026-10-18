# SEO/GEO Foundation — Crawler Policy (Increment C)

Status: implemented. The theme ships no user-agent list and no vendor URL: every token below comes from the vendors' public documentation, verified on 2026-10-08, and every rule comes from the site owner's `config/seo.php`.

## 1. What the theme does and does not do

- `robots.txt` is WordPress' virtual file (or the SEO plugin's body). The theme only appends the groups you declare, after the core block and after the `Sitemap:` line, through the `robots_txt` filter at priority 20.
- Nothing is appended unless `crawlers.policy` is `custom` and `crawlers.groups` is valid. The default (`unset`) leaves `robots.txt` untouched and is reported by the diagnostics as a decision still to take; `open` records the decision to allow everything.
- A physical `robots.txt` in the web root bypasses WordPress entirely; the diagnostics warn when one exists.
- `robots.txt` is never used to hide a staging copy: the indexability policy uses the robots meta directive and the `X-Robots-Tag` header, which crawlers can only read if they are allowed to fetch the page.
- The theme does not add `llms.txt`, Markdown endpoints or any public API, and never changes server, CDN or firewall rules.

## 2. Crawler matrix (vendor documentation, 2026-10-08)

| Vendor         | Token                                                                                                                          | Purpose                                                                       | Honours robots.txt                | Measurable by the theme                              |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------- | --------------------------------- | ---------------------------------------------------- |
| Google         | `Googlebot`, `Googlebot-Image`, `Googlebot-Video`, `Googlebot-News`, `Storebot-Google`, `Google-InspectionTool`, `GoogleOther` | Search indexing, product listings, testing tools, research                    | Yes                               | Rules rendered; crawl behaviour NOT VERIFIED         |
| Google         | `Google-Extended`                                                                                                              | Standalone token governing use of content for Gemini training and grounding   | Yes                               | Same; it does not affect Search inclusion per Google |
| Google         | `Google-CloudVertexBot`                                                                                                        | Vertex AI agents built by site owners                                         | Yes                               | Same                                                 |
| Microsoft Bing | `bingbot`, `adidxbot`, `BingPreview`, `MicrosoftPreview`                                                                       | Search indexing, ads, previews                                                | Yes                               | Same                                                 |
| OpenAI         | `OAI-SearchBot`                                                                                                                | Search results in the assistant                                               | Yes                               | Same                                                 |
| OpenAI         | `GPTBot`                                                                                                                       | Model training                                                                | Yes                               | Same                                                 |
| OpenAI         | `ChatGPT-User`                                                                                                                 | Fetches on a user's request                                                   | Documented as "may not apply"     | NOT VERIFIED; cannot be governed by robots.txt       |
| OpenAI         | `OAI-AdsBot`                                                                                                                   | Advertising                                                                   | Yes                               | Same                                                 |
| Anthropic      | `ClaudeBot`                                                                                                                    | Model training                                                                | Yes (documented)                  | Same                                                 |
| Anthropic      | `Claude-SearchBot`                                                                                                             | Search                                                                        | Yes (documented)                  | Same                                                 |
| Anthropic      | `Claude-User`                                                                                                                  | Fetches on a user's request                                                   | Documented                        | NOT VERIFIED                                         |
| Perplexity     | `PerplexityBot`                                                                                                                | Search, not training                                                          | Implied by the recommended tags   | Same                                                 |
| Perplexity     | `Perplexity-User`                                                                                                              | Fetches on a user's request                                                   | Documented as "generally ignores" | NOT VERIFIED                                         |
| Apple          | `Applebot`, `Applebot-Extended`                                                                                                | Spotlight/Siri search; `-Extended` is the training opt-out and does not crawl | Yes                               | Same                                                 |

Three kinds of access, to be decided separately: **search** (visibility in classic and generative search results), **training** (use of the content to train models), **user-initiated fetching** (an assistant opening a page because a person asked; `robots.txt` is not a control for it, only server-side access control is).

## 3. Configuration

```php
'crawlers' => [
    'policy' => 'custom',          // 'unset' (default), 'open' or 'custom'
    'groups' => [
        ['agents' => ['GPTBot', 'ClaudeBot', 'Google-Extended', 'Applebot-Extended'], 'disallow' => ['/']],
        ['agents' => ['*'], 'disallow' => ['/private/'], 'crawl_delay' => 5],
    ],
],
```

Validation: tokens `[A-Za-z0-9_.*-]` up to 64 characters; paths starting with `/` (wildcards `*` and `$` allowed) up to 200 characters; `crawl_delay` between 1 and 3600 seconds; at most 50 groups; one invalid group invalidates the whole list (reported as FAIL, nothing appended). Rendered output:

```
User-agent: GPTBot
User-agent: ClaudeBot
User-agent: Google-Extended
User-agent: Applebot-Extended
Disallow: /

User-agent: *
Disallow: /private/
Crawl-delay: 5
```

### Example policies

- **Open** (`'policy' => 'open'`): nothing appended; the decision is recorded and the diagnostics stop asking.
- **Training opt-out, search allowed**: disallow `/` for `GPTBot`, `ClaudeBot`, `Google-Extended`, `Applebot-Extended` (and any other training token the owner chooses); leave `OAI-SearchBot`, `Claude-SearchBot`, `PerplexityBot`, `Googlebot`, `bingbot` untouched.
- **Classic search only**: additionally disallow `/` for `OAI-SearchBot`, `Claude-SearchBot` and `PerplexityBot`. This removes the site from those assistants' search results; it is a business decision, not a technical default.

None of these affects Google or Bing rankings per the vendors' own pages; none governs user-initiated fetching.

## 4. Verification limits

| Check                                                 | How                                                                                      | Status in the diagnostics                |
| ----------------------------------------------------- | ---------------------------------------------------------------------------------------- | ---------------------------------------- |
| Rules present in the virtual robots.txt               | End-to-end test on the staging Playground (`tests/e2e/seo-staging.spec.js`), head report | PASS when the policy is custom and valid |
| robots.txt as served to the public                    | Only from outside the site (`curl https://www.example.com/robots.txt`)                   | NOT VERIFIED                             |
| Crawler behaviour (what each bot does with the rules) | Vendor documentation only                                                                | NOT VERIFIED                             |
| CDN, WAF and hosting bot rules                        | Hosting or CDN panel                                                                     | NOT VERIFIED                             |
| User-initiated fetching                               | Not governable by robots.txt                                                             | NOT VERIFIED                             |
| Indexing status                                       | Search Console, Bing Webmaster Tools                                                     | NOT VERIFIED                             |

## 5. Infrastructure checklist (outside the theme)

1. Confirm no physical `robots.txt` exists in the web root, or keep it aligned with the policy.
2. Check CDN and WAF bot-management rules for the agents you intend to allow (some products block AI crawlers by default).
3. Fetch `/robots.txt`, `/wp-sitemap.xml` (or the plugin's index) and a few pages from outside the network and compare with the head report.
4. Keep the staging copy behind HTTP authentication; the theme's `noindex` is the second layer, not the first.
5. Record the decision on training crawlers with the client; revisit it when vendors publish new tokens (the configuration, not the theme, carries the list).
