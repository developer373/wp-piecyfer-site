<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

$site_url = home_url(); // http://localhost/piecyfer
echo "Site URL: $site_url\n";

// Get all menu items with custom URL
$menu_items = $wpdb->get_results("
  SELECT post_id, meta_value 
  FROM {$wpdb->postmeta} 
  WHERE meta_key = '_menu_item_url'
");

$to_update = [];
foreach ($menu_items as $item) {
  $url = $item->meta_value;
  // If it starts with / and not // (like /enterprise-software-development/)
  if (preg_match('#^/([a-zA-Z0-9_\-\#].*)#', $url, $m)) {
    $new_url = $site_url . '/' . ltrim($url, '/');
    $to_update[] = [
      'post_id' => $item->post_id,
      'old_url' => $url,
      'new_url' => $new_url
    ];
  } else if ($url === '/') {
    $to_update[] = [
      'post_id' => $item->post_id,
      'old_url' => $url,
      'new_url' => $site_url . '/'
    ];
  }
}

echo "Found " . count($to_update) . " menu items to update:\n";
foreach ($to_update as $u) {
  $p = get_post($u['post_id']);
  echo "  - [ID: {$u['post_id']}] '{$p->post_title}': {$u['old_url']} => {$u['new_url']}\n";
}
