<?php
require_once __DIR__ . '/../../wp-load.php';

// Find which header template is active
$header_settings = get_option('elementskit_headerfooter_settings');
echo "ElementsKit Header/Footer Settings: " . print_r($header_settings, true) . "\n";

// Let's find all header posts/templates
$posts = get_posts([
  'post_type' => ['elementskit_template', 'elementor_library', 'header'],
  'posts_per_page' => -1,
  'post_status' => 'publish'
]);

foreach ($posts as $p) {
  echo "Template: [ID: {$p->ID}] Title: {$p->post_title}, Post Type: {$p->post_type}\n";
  $type = get_post_meta($p->ID, '_elementskit_template_type', true);
  if ($type) echo "  - EKit Type: $type\n";
}

// Let's search all post_meta for any post containing menu items or mega menu content
global $wpdb;
$mega_items = $wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE '%elementskit_megamenu%' OR meta_key LIKE '%megamenu%'");
echo "\nMega menu post metas:\n";
foreach ($mega_items as $mi) {
  echo "  - Post ID: {$mi->post_id}, Key: {$mi->meta_key}, Val: " . substr($mi->meta_value, 0, 100) . "\n";
}

// Let's also check all nav menu items in wp_postmeta
$menu_custom_urls = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_url'");
echo "\nAll Custom Menu Item URLs:\n";
foreach ($menu_custom_urls as $u) {
  if (strpos($u->meta_value, 'emerging-tech') !== false || strpos($u->meta_value, 'digital-marketing') !== false || strpos($u->meta_value, 'hire-an-expert') !== false || strpos($u->meta_value, 'enterprise') !== false || strpos($u->meta_value, 'http') === 0 || strpos($u->meta_value, '/') === 0) {
    $parent = get_post($u->post_id);
    echo "  - Item ID {$u->post_id} ('{$parent->post_title}'): {$u->meta_value}\n";
  }
}
