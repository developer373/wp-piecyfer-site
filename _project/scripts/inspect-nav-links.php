<?php
require_once __DIR__ . '/../../wp-load.php';

// 1. Inspect registered nav menus and menu items
$menus = wp_get_nav_menus();
echo "=== WP NAV MENUS ===\n";
foreach ($menus as $m) {
  echo "Menu: {$m->name} (ID: {$m->term_id}, Slug: {$m->slug})\n";
  $items = wp_get_nav_menu_items($m->term_id);
  if ($items) {
    foreach ($items as $item) {
      if (strpos($item->url, 'localhost') !== false || strpos($item->url, 'piecyfer.com') !== false || strpos($item->url, '/') === 0 || strpos($item->url, 'http') === 0) {
        echo "  - [ID: {$item->ID}] {$item->title} => {$item->url} (type: {$item->type}, object_id: {$item->object_id})\n";
      }
    }
  }
}

// 2. Check header templates in elementor_library or other post types
$header_posts = get_posts([
  'post_type' => ['elementor_library', 'ekit_template', 'header', 'wp_navigation', 'page'],
  'posts_per_page' => -1,
  'post_status' => 'publish'
]);

echo "\n=== HEADER / TEMPLATE POSTS ===\n";
foreach ($header_posts as $hp) {
  $data = get_post_meta($hp->ID, '_elementor_data', true);
  if (is_string($data) && (strpos($data, 'emerging-tech') !== false || strpos($data, 'enterprise-software-development') !== false || strpos($data, 'digital-marketing') !== false || strpos($data, 'hire-an-expert') !== false)) {
    echo "Template with links: [ID: {$hp->ID}] Title: {$hp->post_title}, Type: {$hp->post_type}\n";
  }
}
