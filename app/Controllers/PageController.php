<?php

declare(strict_types=1);

namespace Understack\Controllers;

use Timber\Post;
use Timber\Timber;
use Understack\Core\PageTemplates;

final class PageController extends Controller
{
    public function handle(): void
    {
        $context = $this->context();
        $post = $context['post'] ?? null;
        $templates = [];

        $assigned = PageTemplates::currentView();

        if ($assigned !== null) {
            $templates[] = $assigned;
        }

        if ($post instanceof Post) {
            if (is_front_page()) {
                $templates[] = 'pages/front-page.twig';
                $context['latest_posts'] = Timber::get_posts([
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'posts_per_page' => 3,
                    'ignore_sticky_posts' => true,
                ]);
                $context['posts_page_link'] = $this->postsPageLink();
            }

            $templates[] = 'pages/page-' . $post->slug . '.twig';
            $templates[] = 'pages/page-' . $post->ID . '.twig';
        }

        $templates[] = 'pages/page.twig';

        $this->render($templates, $context);
    }

    private function postsPageLink(): string
    {
        $pageForPosts = (int) get_option('page_for_posts');
        $link = $pageForPosts > 0 ? get_permalink($pageForPosts) : get_post_type_archive_link('post');

        return is_string($link) ? $link : '';
    }
}
