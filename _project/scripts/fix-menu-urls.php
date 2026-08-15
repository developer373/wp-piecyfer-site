<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

$site_url = home_url(); // http://localhost/piecyfer

$menu_items = $wpdb->get_results("
  SELECT post_id, meta_value 
  FROM {$wpdb->postmeta} 
  WHERE meta_key = '_menu_item_url'
");

$updated_count = 0;
foreach ($menu_items as $item) {
  $url = $item->meta_value;
  $new_url = null;

  if (preg_match('#^/([a-zA-Z0-9_\-\#].*)#', $url, $m)) {
    $new_url = $site_url . '/' . ltrim($url, '/');
  } else if ($url === '/') {
    $new_url = $site_url . '/';
  }

  if ($new_url) {
    update_post_meta($item->post_id, '_menu_item_url', $new_url);
    $p = get_post($item->post_id);
    echo "Updated [ID: {$item->post_id}] '{$p->post_title}': {$url} => {$new_url}\n";
    $updated_count++;
  }
}

echo "\nTotal menu items updated: $updated_count\n";

// Clear any transients and caches
wp_cache_flush();
