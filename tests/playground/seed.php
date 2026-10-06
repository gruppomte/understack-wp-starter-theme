<?php

/**
 * Sample content for the disposable WordPress Playground site used by the
 * end-to-end tests (see scripts/playground.mjs). Idempotent.
 */

declare(strict_types=1);

require_once '/wordpress/wp-load.php';

if (get_option('theme_playground_seeded')) {
    return;
}

update_option('blogname', 'Playground');
update_option('blogdescription', 'Theme verification site');
update_option('page_comments', 1);
update_option('comments_per_page', 2);
update_option('thread_comments', 1);

global $wp_rewrite;
$wp_rewrite->set_permalink_structure('/%postname%/');
$wp_rewrite->flush_rules(false);

$category = wp_insert_term('News', 'category');
$categoryId = is_array($category) ? (int) $category['term_id'] : 1;

$about = (int) wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'About us',
    'post_name' => 'about',
    'post_content' => '<!-- wp:heading --><h2>Who we are</h2><!-- /wp:heading -->'
        . '<!-- wp:paragraph --><p>A short introduction with <a href="#">a link</a> and '
        . '<strong>bold text</strong>.</p><!-- /wp:paragraph -->'
        . '<!-- wp:list --><ul><li>First point</li><li>Second point</li></ul><!-- /wp:list -->',
]);

$team = (int) wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Team',
    'post_name' => 'team',
    'post_parent' => $about,
    'post_content' => '<!-- wp:paragraph --><p>Team page.</p><!-- /wp:paragraph -->',
]);

$landing = (int) wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Landing',
    'post_name' => 'landing',
    'post_content' => '<!-- wp:acf/hero ' . wp_json_encode([
        'name' => 'acf/hero',
        'data' => [
            'heading' => 'Hero block heading',
            '_heading' => 'field_68e3c1a2b0f11',
            'heading_is_title' => '1',
            '_heading_is_title' => 'field_68e3c1a2b0f12',
            'text' => 'Hero block text.',
            '_text' => 'field_68e3c1a2b0f13',
            'link' => ['title' => 'Get in touch', 'url' => '/about/', 'target' => ''],
            '_link' => 'field_68e3c1a2b0f14',
            'image_position' => 'end',
            '_image_position' => 'field_68e3c1a2b0f16',
        ],
        'mode' => 'preview',
    ]) . ' /-->'
        . '<!-- wp:paragraph --><p>Landing page body.</p><!-- /wp:paragraph -->',
]);
update_post_meta($landing, '_wp_page_template', 'landing');
update_post_meta($landing, 'page_hide_title', '1');

$postIds = [];

for ($index = 1; $index <= 4; $index++) {
    $postIds[] = (int) wp_insert_post([
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_title' => "Sample post {$index} with an apostrophe's entity",
        'post_name' => "sample-post-{$index}",
        'post_content' => "<!-- wp:paragraph --><p>Body of post {$index}.</p><!-- /wp:paragraph -->",
        'post_category' => [$categoryId],
        'tags_input' => ['sample'],
    ]);
}

foreach (['Alice', 'Bob', 'Carla'] as $index => $author) {
    wp_insert_comment([
        'comment_post_ID' => $postIds[0],
        'comment_author' => $author,
        'comment_author_email' => strtolower($author) . '@example.com',
        'comment_content' => 'Comment number ' . ($index + 1) . '.',
        'comment_approved' => 1,
    ]);
}

$menuId = (int) wp_create_nav_menu('Primary');
$aboutItem = (int) wp_update_nav_menu_item($menuId, 0, [
    'menu-item-title' => 'About us',
    'menu-item-object' => 'page',
    'menu-item-object-id' => $about,
    'menu-item-type' => 'post_type',
    'menu-item-status' => 'publish',
]);
wp_update_nav_menu_item($menuId, 0, [
    'menu-item-title' => 'Team',
    'menu-item-object' => 'page',
    'menu-item-object-id' => $team,
    'menu-item-type' => 'post_type',
    'menu-item-status' => 'publish',
    'menu-item-parent-id' => $aboutItem,
]);
wp_update_nav_menu_item($menuId, 0, [
    'menu-item-title' => 'Landing',
    'menu-item-object' => 'page',
    'menu-item-object-id' => $landing,
    'menu-item-type' => 'post_type',
    'menu-item-status' => 'publish',
]);
set_theme_mod('nav_menu_locations', ['primary' => $menuId, 'footer' => $menuId]);

update_option('theme_playground_seeded', 1);
