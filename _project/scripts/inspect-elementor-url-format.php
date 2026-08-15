<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

$posts = $wpdb->get_results("
  SELECT post_id, meta_value 
  FROM {$wpdb->postmeta} 
  WHERE meta_key = '_elementor_data' 
    AND (meta_value LIKE '%hire-an-expert%' 
      OR meta_value LIKE '%digital-marketing%' 
      OR meta_value LIKE '%emerging-tech%')
");

echo "Found " . count($posts) . " posts matching keywords.\n";

$site_url = home_url(); // http://localhost/piecyfer
// Escaped site URL: http:\/\/localhost\/piecyfer
$escaped_site_url = str_replace('/', '\/', $site_url);

foreach ($posts as $p) {
  $data = $p->meta_value;
  // Let's see how the url is stored
  if (preg_match_all('#"url":"([^"]+)"#', $data, $matches)) {
    foreach ($matches[1] as $u) {
      if (strpos($u, 'hire-an-expert') !== false || strpos($u, 'digital-marketing') !== false || strpos($u, 'emerging-tech') !== false) {
        echo "Post {$p->post_id}: found url: $u\n";
      }
    }
  }
}
