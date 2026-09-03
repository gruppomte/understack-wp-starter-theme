<?php

declare(strict_types=1);

namespace Understack\Controllers;

use WP_Term;

final class ArchiveController extends Controller
{
    public function handle(): void
    {
        $context = $this->context([
            'title' => get_the_archive_title(),
            'description' => get_the_archive_description(),
        ]);

        $context['pagination'] = isset($context['posts']) ? $context['posts']->pagination() : null;

        $this->render($this->templates(), $context);
    }

    /**
     * @return string[]
     */
    private function templates(): array
    {
        $templates = [];

        if (is_post_type_archive()) {
            $type = (string) get_query_var('post_type');
            $templates[] = 'archives/archive-' . sanitize_key($type) . '.twig';
        }

        if (is_category()) {
            $templates[] = 'archives/category.twig';
        }

        if (is_tag()) {
            $templates[] = 'archives/tag.twig';
        }

        if (is_tax()) {
            $term = get_queried_object();

            if ($term instanceof WP_Term) {
                $templates[] = 'archives/taxonomy-' . sanitize_key($term->taxonomy) . '.twig';
            }

            $templates[] = 'archives/taxonomy.twig';
        }

        if (is_author()) {
            $templates[] = 'archives/author.twig';
        }

        if (is_date()) {
            $templates[] = 'archives/date.twig';
        }

        $templates[] = 'archives/archive.twig';

        return $templates;
    }
}
