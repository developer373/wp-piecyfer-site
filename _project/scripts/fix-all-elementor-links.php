<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

$site_url = home_url(); // http://localhost/piecyfer
$escaped_site = str_replace('/', '\/', $site_url);

$posts = $wpdb->get_results("
  SELECT post_id, meta_value 
  FROM {$wpdb->postmeta} 
  WHERE meta_key = '_elementor_data'
");

$updated_posts = 0;
$targets = [
  'emerging-tech',
  'digital-marketing',
  'hire-an-expert',
  'enterprise-software-development',
  'web-app-development',
  'mobile-app-development',
  'software-quality-testing',
  'crm-solutions',
  'cms-solutions',
  'cloud-services',
  'software-re-engineering',
  'software-migration',
  'software-maintenance-and-support',
  'about-piecyfer',
  'why-choose-us',
  'our-team',
  'contact-us',
  'blogs'
];

foreach ($posts as $p) {
  $data = $p->meta_value;
  $new_data = $data;

  foreach ($targets as $slug) {
    // Case 1: "url":"\/slug
    $new_data = str_replace('"url":"\/' . $slug, '"url":"' . $escaped_site . '\/' . $slug, $new_data);
    // Case 2: href=\"/slug
    $new_data = str_replace('href=\"/' . $slug, 'href=\"' . $site_url . '/' . $slug, $new_data);
    // Case 3: href=\\"/slug
    $new_data = str_replace('href=\\"/' . $slug, 'href=\\"' . $site_url . '/' . $slug, $new_data);
  }

  if ($new_data !== $data) {
    update_post_meta($p->post_id, '_elementor_data', wp_slash($new_data));
    echo "Updated Elementor post ID: {$p->post_id}\n";
    $updated_posts++;
  }
}

echo "Total Elementor posts updated: $updated_posts\n";

\Elementor\Plugin::$instance->files_manager->clear_cache();
wp_cache_flush();
echo "Flushed all Elementor cache.\n";
