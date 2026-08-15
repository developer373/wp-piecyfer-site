<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

echo "=== SCANNING POSTMETA FOR BROKEN ROOT-RELATIVE PATHS ===\n";

$bad_paths = [
  '/enterprise-software-development',
  '/emerging-tech',
  '/digital-marketing',
  '/hire-an-expert',
  '/about-piecyfer',
  '/why-choose-us',
  '/our-team',
  '/contact-us',
  '/blogs',
  '/web-app-development',
  '/mobile-app-development',
  '/software-quality-testing',
  '/cloud-services',
  '/cms-solutions',
  '/crm-solutions',
  '/software-maintenance-and-support',
  '/software-migration',
  '/software-re-engineering'
];

$posts = $wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE '%/enterprise-software-development%' OR meta_value LIKE '%/emerging-tech%' OR meta_value LIKE '%/digital-marketing%' OR meta_value LIKE '%/hire-an-expert%'");

$found_in_elementor = [];
foreach ($posts as $row) {
  if ($row->meta_key === '_menu_item_url') continue;
  
  $post = get_post($row->post_id);
  echo "Found in Post ID {$row->post_id} ('{$post->post_title}', type: {$post->post_type}), Meta Key: {$row->meta_key}\n";
  if ($row->meta_key === '_elementor_data') {
    $found_in_elementor[] = $row->post_id;
  }
}

echo "Found in " . count($found_in_elementor) . " Elementor data posts.\n";
