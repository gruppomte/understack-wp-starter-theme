<?php

declare(strict_types=1);

namespace Understack\Controllers;

use Timber\CommentThread;
use Timber\Post;

final class SingleController extends Controller
{
    public function handle(): void
    {
        $context = $this->context();
        $post = $context['post'] ?? null;
        $templates = [];

        if ($post instanceof Post) {
            $type = $post->post_type;
            $templates[] = 'singles/single-' . $type . '-' . $post->slug . '.twig';
            $templates[] = 'singles/single-' . $type . '.twig';
            $context = array_merge($context, $this->comments($post));
        }

        $templates[] = 'singles/single.twig';

        $this->render($templates, $context);
    }

    /**
     * Comments of the requested thread page, honouring the "Break comments into
     * pages" discussion settings (per page, default page, threading).
     *
     * @return array{comments: iterable<mixed>, comments_pagination: string}
     */
    private function comments(Post $post): array
    {
        if (! get_option('page_comments')) {
            return ['comments' => $post->comments(), 'comments_pagination' => ''];
        }

        $perPage = max(1, (int) get_option('comments_per_page'));
        $threaded = (bool) get_option('thread_comments');

        $countArguments = ['post_id' => $post->ID, 'status' => 'approve', 'count' => true];

        if ($threaded) {
            $countArguments['parent'] = 0;
        }

        $pages = (int) ceil(((int) get_comments($countArguments)) / $perPage);
        $current = (int) get_query_var('cpage');

        if ($current < 1) {
            $current = get_option('default_comments_page') === 'newest' ? max(1, $pages) : 1;
            set_query_var('cpage', $current);
        }

        $arguments = [
            'order' => get_option('comment_order'),
            'number' => $perPage,
            'offset' => ($current - 1) * $perPage,
            'hierarchical' => $threaded ? 'flat' : false,
        ];

        $commenter = wp_get_current_commenter();

        if (is_user_logged_in()) {
            $arguments['include_unapproved'] = [get_current_user_id()];
        } elseif ($commenter['comment_author_email'] !== '') {
            $arguments['include_unapproved'] = [$commenter['comment_author_email']];
        }

        $thread = new CommentThread($post->ID, false);
        $thread->init($arguments);

        $pagination = $pages > 1
            ? (string) paginate_comments_links(['echo' => false, 'total' => $pages, 'current' => $current])
            : '';

        return ['comments' => $thread, 'comments_pagination' => $pagination];
    }
}
