<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

$site_url = home_url(); // http://localhost/piecyfer

// Find all posts where _elementor_data contains root-relative URLs
$posts = $wpdb->get_results("
  SELECT post_id, meta_value 
  FROM {$wpdb->postmeta} 
  WHERE meta_key = '_elementor_data' 
    AND (meta_value LIKE '%\"/hire-an-expert/%' 
      OR meta_value LIKE '%\"/emerging-tech/%' 
      OR meta_value LIKE '%\"/digital-marketing/%' 
      OR meta_value LIKE '%\"/enterprise-software-development/%')
");

echo "Found " . count($posts) . " elementor posts to fix.\n";

$replacements = [
  '"/hire-an-expert/' => '"' . $site_url . '/hire-an-expert/',
  '"/emerging-tech/' => '"' . $site_url . '/emerging-tech/',
  '"/digital-marketing/' => '"' . $site_url . '/digital-marketing/',
  '"/enterprise-software-development/' => '"' . $site_url . '/enterprise-software-development/',
  '\"/hire-an-expert/' => '\"' . $site_url . '/hire-an-expert/',
  '\"/emerging-tech/' => '\"' . $site_url . '/emerging-tech/',
  '\"/digital-marketing/' => '\"' . $site_url . '/digital-marketing/',
  '\"/enterprise-software-development/' => '\"' . $site_url . '/enterprise-software-development/',
];

foreach ($posts as $p) {
  $data = $p->meta_value;
  $new_data = str_replace(array_keys($replacements), array_values($replacements), $data);
  if ($new_data !== $data) {
    update_post_meta($p->post_id, '_elementor_data', wp_slash($new_data));
    echo "Updated Post ID: {$p->post_id}\n";
  }
}

// Clear Elementor CSS cache
\Elementor\Plugin::$instance->files_manager->clear_cache();
wp_cache_flush();
echo "Elementor cache cleared!\n";
