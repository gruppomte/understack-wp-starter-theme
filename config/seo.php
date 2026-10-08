<?php

/**
 * SEO/GEO foundation settings for this site. Return only the keys to change;
 * every other key keeps the default listed in app/Seo/Config.php. The same
 * keys can be overridden at runtime with the "<theme slug>/seo/config" filter.
 * Full reference: docs/seo-geo/03-configuration.md.
 *
 * Keys and defaults:
 * - enabled (true): master switch for every theme-side SEO output.
 * - provider ("auto"): "auto" detects the active SEO plugin; "none" forces the
 *   fallbacks; "rank-math", "yoast", "seopress", "aioseo", "the-seo-framework"
 *   or "slim-seo" forces silence as if that plugin were active.
 * - fallbacks.description / canonical / social (true): emitted only without a plugin.
 * - with_provider ([]): signals to keep emitting while a plugin is active
 *   ("description", "canonical", "social"); you then own the duplicates.
 * - indexability.non_production ("noindex" | "off"): noindex, nofollow and an
 *   X-Robots-Tag header when WP_ENVIRONMENT_TYPE is local, development or staging.
 * - indexability.production_hosts ([]): optional explicit list of production
 *   hosts; a mismatch with the environment type is reported, never guessed.
 * - indexability.x_robots_tag (true): send the header on non-production copies.
 * - indexability.not_found / attachments ("noindex" | "index").
 * - indexability.post_types / taxonomies ([]): extra noindex types, also removed
 *   from the core sitemap.
 * - sitemap.exclude_noindex (true), sitemap.users (false): core sitemap filters.
 * - social.twitter_card ("summary_large_image" | "summary"), social.twitter_site
 *   (""), social.image_size ("wide"), social.default_image (0, attachment ID).
 * - content.article_post_types (["post"]): post types presented as articles.
 */

declare(strict_types=1);

return [];
