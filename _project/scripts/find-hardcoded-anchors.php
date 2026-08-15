<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

echo "=== SEARCHING FOR `<a href=\"/emerging-tech/\">` ETC ===\n";

$rows = $wpdb->get_results("
  SELECT post_id, meta_key, meta_value 
  FROM {$wpdb->postmeta} 
  WHERE meta_value LIKE '%href=\"/emerging-tech/%' 
     OR meta_value LIKE '%href=\"/digital-marketing/%' 
     OR meta_value LIKE '%href=\"/hire-an-expert/%'
     OR meta_value LIKE '%href=\\\"/emerging-tech/%' 
     OR meta_value LIKE '%href=\\\"/digital-marketing/%' 
     OR meta_value LIKE '%href=\\\"/hire-an-expert/%'
");

foreach ($rows as $r) {
  $p = get_post($r->post_id);
  echo "Found in Post ID {$r->post_id} ('{$p->post_title}', type: {$p->post_type}), Meta Key: {$r->meta_key}\n";
}

// Check wp_options as well
$opt_rows = $wpdb->get_results("
  SELECT option_name 
  FROM {$wpdb->options} 
  WHERE option_value LIKE '%href=\"/emerging-tech/%' 
     OR option_value LIKE '%href=\"/digital-marketing/%' 
     OR option_value LIKE '%href=\"/hire-an-expert/%'
");

foreach ($opt_rows as $o) {
  echo "Found in Option: {$o->option_name}\n";
}
