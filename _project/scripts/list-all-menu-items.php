<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

// Let's get all menu items across all menus
$menu_items = $wpdb->get_results("
  SELECT p.ID, p.post_title, pm_url.meta_value as url, pm_type.meta_value as item_type, pm_obj.meta_value as object_id
  FROM {$wpdb->posts} p
  LEFT JOIN {$wpdb->postmeta} pm_url ON p.ID = pm_url.post_id AND pm_url.meta_key = '_menu_item_url'
  LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = '_menu_item_type'
  LEFT JOIN {$wpdb->postmeta} pm_obj ON p.ID = pm_obj.post_id AND pm_obj.meta_key = '_menu_item_object_id'
  WHERE p.post_type = 'nav_menu_item'
");

echo "Total menu items found: " . count($menu_items) . "\n\n";

$broken_items = [];
foreach ($menu_items as $item) {
  $url = $item->url;
  $title = $item->post_title;
  // If it's a post_type item, get the permalink
  if ($item->item_type === 'post_type') {
    $real_url = get_permalink($item->object_id);
    echo "[POST_TYPE] ID: {$item->ID} | Title: {$title} | Object: {$item->object_id} | Permalink: {$real_url}\n";
  } else {
    echo "[CUSTOM] ID: {$item->ID} | Title: {$title} | URL: {$url}\n";
    if (strpos($url, '/') === 0 || (strpos($url, 'http://localhost/') === 0 && strpos($url, 'http://localhost/piecyfer') === false)) {
      $broken_items[] = $item;
    }
  }
}

echo "\n--- BROKEN CUSTOM ITEMS (" . count($broken_items) . ") ---\n";
foreach ($broken_items as $b) {
  echo "ID: {$b->ID} | Title: '{$b->post_title}' | URL: '{$b->url}'\n";
}
