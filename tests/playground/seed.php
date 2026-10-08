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
// Two posts per page so listings paginate (canonical and description checks on /page/2/).
update_option('posts_per_page', 2);
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

// Structured data fixtures: an explicit About page, a Contact page and a public author.
update_post_meta($about, 'page_schema_type', 'AboutPage');
update_post_meta($about, '_page_schema_type', 'field_68e5b1c2d3e01');

$contact = (int) wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Contact',
    'post_name' => 'contact',
    'post_content' => '<!-- wp:paragraph --><p>Write to us.</p><!-- /wp:paragraph -->',
]);
update_post_meta($contact, 'page_schema_type', 'ContactPage');
update_post_meta($contact, '_page_schema_type', 'field_68e5b1c2d3e01');

// "alice" has a public display name; the administrator keeps its login as display name.
$alice = username_exists('alice') ?: (int) wp_insert_user([
    'user_login' => 'alice',
    'user_pass' => wp_generate_password(24),
    'user_email' => 'alice@example.com',
    'display_name' => 'Alice Rossi',
    'first_name' => 'Alice',
    'last_name' => 'Rossi',
    'role' => 'author',
    'description' => 'Writes the sample posts.',
    'user_url' => 'https://alice.example',
]);

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
        'post_author' => $index === 1 ? 1 : (int) $alice,
    ]);
}

// Organisation identity on the Site Settings page (ACF option fields).
if (function_exists('update_field')) {
    $logoId = 0;

    if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
        $image = imagecreatetruecolor(300, 300);
        imagefilledrectangle($image, 0, 0, 299, 299, (int) imagecolorallocate($image, 20, 40, 80));
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        $upload = wp_upload_bits('playground-logo.png', null, $png);

        if (empty($upload['error']) && is_string($upload['file'] ?? null)) {
            $logoId = (int) wp_insert_attachment([
                'post_mime_type' => 'image/png',
                'post_title' => 'Playground logo',
                'post_status' => 'inherit',
            ], $upload['file']);
            require_once ABSPATH . 'wp-admin/includes/image.php';
            wp_update_attachment_metadata($logoId, wp_generate_attachment_metadata($logoId, $upload['file']));
            update_post_meta($logoId, '_wp_attachment_image_alt', 'Playground logo');
        }
    }

    update_field('org_type', 'Organization', 'option');
    update_field('org_name', 'Playground Organisation', 'option');
    update_field('org_legal_name', 'Playground Organisation Ltd', 'option');
    update_field('org_description', 'Fixture organisation used by the end-to-end tests.', 'option');
    update_field('org_email', 'info@example.com', 'option');
    update_field('org_telephone', '+44 20 7946 0000', 'option');
    update_field('org_address', [
        'street' => '1 Example Street',
        'locality' => 'London',
        'region' => '',
        'postal_code' => 'EC1A 1AA',
        'country' => 'GB',
    ], 'option');
    update_field('org_areas_served', [['area' => 'London'], ['area' => 'United Kingdom']], 'option');
    update_field('org_same_as', [['url' => 'https://www.wikidata.org/wiki/Q42']], 'option');
    update_field(
        'social_links',
        [['label' => 'Example profile', 'url' => 'https://social.example/playground']],
        'option'
    );

    if ($logoId > 0) {
        update_field('org_logo', $logoId, 'option');
    }
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
