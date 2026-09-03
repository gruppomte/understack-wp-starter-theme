<?php

declare(strict_types=1);

namespace Understack\Controllers;

final class IndexController extends Controller
{
    public function handle(): void
    {
        $pageForPosts = (int) get_option('page_for_posts');

        $context = $this->context([
            'title' => $pageForPosts > 0 && is_home()
                ? get_the_title($pageForPosts)
                : __('Latest posts', 'understack-wp-starter'),
            'description' => '',
        ]);

        $context['pagination'] = isset($context['posts']) ? $context['posts']->pagination() : null;

        $this->render(['archives/index.twig', 'archives/archive.twig'], $context);
    }
}
