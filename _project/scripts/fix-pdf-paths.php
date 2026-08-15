<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

echo "=== SEARCHING FOR PDF PATHS ===\n";

$rows = $wpdb->get_results("
  SELECT post_id, meta_key, meta_value 
  FROM {$wpdb->postmeta} 
  WHERE meta_value LIKE '%Email_Marketing_CRM.pdf%' 
     OR meta_value LIKE '%Human_Resource_TMS.pdf%' 
     OR meta_value LIKE '%onlinedoc.pdf%'
");

foreach ($rows as $r) {
  $p = get_post($r->post_id);
  echo "Found in Post ID {$r->post_id} ('{$p->post_title}', type: {$p->post_type}), Meta Key: {$r->meta_key}\n";
  
  $site_url = home_url();
  $escaped_site = str_replace('/', '\/', $site_url);
  
  $data = $r->meta_value;
  $new_data = $data;
  
  // Replace unescaped /wp-content/uploads/
  $new_data = str_replace('href="/wp-content/uploads/', 'href="' . $site_url . '/wp-content/uploads/', $new_data);
  $new_data = str_replace('"url":"/wp-content/uploads/', '"url":"' . $site_url . '/wp-content/uploads/', $new_data);
  
  // Replace escaped \/wp-content\/uploads\/
  $new_data = str_replace('"url":"\/wp-content\/uploads\/', '"url":"' . $escaped_site . '\/wp-content\/uploads\/', $new_data);
  $new_data = str_replace('href=\"/wp-content/uploads/', 'href=\"' . $site_url . '/wp-content/uploads/', $new_data);
  $new_data = str_replace('href=\\"/wp-content/uploads/', 'href=\\"' . $site_url . '/wp-content/uploads/', $new_data);

  if ($new_data !== $data) {
    update_post_meta($r->post_id, $r->meta_key, wp_slash($new_data));
    echo "  -> UPDATED Post ID: {$r->post_id}, Meta Key: {$r->meta_key}\n";
  }
}

// Clear all Elementor CSS & cache
\Elementor\Plugin::$instance->files_manager->clear_cache();
wp_cache_flush();
echo "Flushed caches.\n";
