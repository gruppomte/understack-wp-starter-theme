<?php

declare(strict_types=1);

namespace Understack\Seo\StructuredData;

use Understack\Core\Theme;
use Understack\Seo\Config;
use Understack\Seo\Meta\Canonical;
use Understack\Seo\Meta\Description;
use Understack\Seo\Meta\Social;
use Understack\Seo\PageContext;
use Understack\Seo\PageKind;
use Understack\Seo\Seo;
use Understack\Seo\Text;
use WP_Post;
use WP_User;

/**
 * Gathers the graph input from WordPress for the current request.
 */
final class Collector
{
    public function __construct(private readonly Seo $seo)
    {
    }

    public function collect(): Input
    {
        $context = $this->seo->context();
        $config = $this->seo->config();
        $canonical = $this->canonical($context);
        $post = $context->isSingular() ? get_post($context->objectId) : null;
        $post = $post instanceof WP_Post ? $post : null;
        $article = $post !== null ? $this->article($post, $context, $config) : null;
        $author = $article !== null ? $this->author((int) $post->post_author) : null;
        $pageType = $this->pageType($context, $post, $config);
        $profile = $pageType === 'ProfilePage' ? $this->author($context->objectId) : null;
        $pageType = $pageType === 'ProfilePage' && $profile === null ? 'CollectionPage' : $pageType;
        $dates = $post !== null ? self::dates($post) : ['', ''];

        return new Input(
            home: home_url('/'),
            siteName: Text::plain(get_bloginfo('name')),
            language: str_replace('_', '-', get_locale()),
            tagline: Text::plain(get_bloginfo('description')),
            identity: $this->seo->identity(),
            canonical: $canonical,
            pageType: $pageType,
            title: Text::plain(wp_get_document_title()),
            description: Description::resolve($context),
            datePublished: $dates[0],
            dateModified: $dates[1],
            article: $article,
            author: $author,
            authorUrl: $author !== null && $config->bool('structured_data.author_profiles')
                ? get_author_posts_url((int) $post->post_author)
                : '',
            image: $post !== null ? Social::featuredImage($post->ID, $config) : null,
            breadcrumbs: $this->seo->ownership()->themeOwns(\Understack\Seo\Signal::Breadcrumbs)
                ? $this->seo->breadcrumbs()->items()
                : [],
            profile: $profile,
            profileUrl: $profile !== null ? get_author_posts_url($context->objectId) : '',
        );
    }

    private function canonical(PageContext $context): string
    {
        if (! $context->isIndexable()) {
            return '';
        }

        if ($context->isSingular()) {
            $url = wp_get_canonical_url($context->objectId);

            return is_string($url) ? $url : '';
        }

        return (string) Canonical::resolve($context);
    }

    /**
     * WebPage subtype: an explicit editorial choice on the page, else the
     * configured About or Contact page ID, else the kind of listing.
     */
    private function pageType(PageContext $context, ?WP_Post $post, Config $config): string
    {
        if ($post !== null) {
            $override = (string) get_post_meta($post->ID, 'page_schema_type', true);

            if (in_array($override, ['AboutPage', 'ContactPage'], true)) {
                return $override;
            }

            foreach (['about_page' => 'AboutPage', 'contact_page' => 'ContactPage'] as $key => $type) {
                /**
                 * Filters a configured page ID before it is compared with the current
                 * page (a multilingual plugin can map it to the translated page).
                 *
                 * @param int $id Page ID from the configuration.
                 * @param string $role "about_page" or "contact_page".
                 */
                $id = (int) apply_filters(Theme::SLUG . '/seo/page_id', $config->int('structured_data.' . $key), $key);

                if ($id > 0 && $id === (int) $post->ID) {
                    return $type;
                }
            }

            return 'WebPage';
        }

        if ($context->kind === PageKind::Author && $config->bool('structured_data.author_profiles')) {
            return 'ProfilePage';
        }

        return 'CollectionPage';
    }

    /**
     * @return array{
     *     type: string,
     *     headline: string,
     *     published: string,
     *     modified: string,
     *     sections: string[],
     *     wordCount: int
     * }|null
     */
    private function article(WP_Post $post, PageContext $context, Config $config): ?array
    {
        if (
            ! $context->published
            || $context->passwordProtected
            || ! in_array($post->post_type, $config->list('content.article_post_types'), true)
        ) {
            return null;
        }

        $sections = [];

        foreach ((array) get_the_terms($post->ID, 'category') as $term) {
            if ($term instanceof \WP_Term) {
                $sections[] = Text::plain($term->name);
            }
        }

        [$published, $modified] = self::dates($post);

        return [
            'type' => $post->post_type === 'post' ? 'BlogPosting' : 'Article',
            'headline' => Text::plain(get_the_title($post)),
            'published' => $published,
            'modified' => $modified,
            'sections' => $sections,
            'wordCount' => (int) preg_match_all(
                '/\p{L}[\p{L}\p{M}\p{N}\'’-]*/u',
                Text::plain(strip_shortcodes($post->post_content))
            ),
        ];
    }

    /**
     * @return array{id: int, name: string, description: string, url: string}|null
     */
    private function author(int $userId): ?array
    {
        $user = $userId > 0 ? get_userdata($userId) : false;

        if (! $user instanceof WP_User) {
            return null;
        }

        $author = Author::fromUser([
            'id' => (int) $user->ID,
            'display_name' => (string) $user->display_name,
            'user_login' => (string) $user->user_login,
            'user_nicename' => (string) $user->user_nicename,
            'user_email' => (string) $user->user_email,
            'description' => (string) get_the_author_meta('description', $user->ID),
            'user_url' => (string) $user->user_url,
        ]);

        // A website field pointing at this site (the WordPress default for the first user) is not an external profile.
        if ($author !== null && str_starts_with(trailingslashit($author['url']), home_url('/'))) {
            $author['url'] = '';
        }

        return $author;
    }

    /**
     * @return array{0: string, 1: string} Published and modified dates, ISO 8601 with offset.
     */
    private static function dates(WP_Post $post): array
    {
        $published = get_post_datetime($post);
        $modified = get_post_datetime($post, 'modified');

        return [
            $published ? $published->format(DATE_ATOM) : '',
            $modified ? $modified->format(DATE_ATOM) : '',
        ];
    }
}
