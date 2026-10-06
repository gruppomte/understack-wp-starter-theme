<?php

declare(strict_types=1);

namespace Understack\Controllers;

final class SearchController extends Controller
{
    public function handle(): void
    {
        $context = $this->context([
            'title' => sprintf(
                /* translators: %s: search query. */
                __('Search results for "%s"', 'understack-wp-starter'),
                get_search_query()
            ),
            'description' => '',
            // The results page already shows a search form.
            'show_search_form' => false,
        ]);

        $context['pagination'] = isset($context['posts']) ? $context['posts']->pagination() : null;

        $this->render(['archives/search.twig', 'archives/archive.twig'], $context);
    }
}
