<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

$posts = $wpdb->get_results("
  SELECT ID, post_title, post_content 
  FROM {$wpdb->posts} 
  WHERE post_name LIKE '%why-healthcare-companies-are-finally-ditching-legacy-systems-in-2026%' 
     OR post_title LIKE '%Why Healthcare Companies%'
");

foreach ($posts as $p) {
  echo "Found Post ID: {$p->ID}, Title: {$p->post_title}\n";
  if (strpos($p->post_content, 'crm-solutions') !== false) {
    echo "  - Found in post_content!\n";
    $fixed = preg_replace('#href=["\']/crm-solutions/?["\']#i', 'href="' . home_url('/crm-solutions/') . '"', $p->post_content);
    $wpdb->update($wpdb->posts, ['post_content' => $fixed], ['ID' => $p->ID]);
    echo "  - Updated post_content for ID {$p->ID}!\n";
  }

  $elem = get_post_meta($p->ID, '_elementor_data', true);
  if ($elem && strpos($elem, 'crm-solutions') !== false) {
    echo "  - Found in _elementor_data!\n";
    $fixed_elem = str_replace(['\/crm-solutions\/', '\/crm-solutions', '/crm-solutions/', '/crm-solutions'], [str_replace('/', '\/', home_url('/crm-solutions/')), str_replace('/', '\/', home_url('/crm-solutions/')), home_url('/crm-solutions/'), home_url('/crm-solutions/')], $elem);
    update_post_meta($p->ID, '_elementor_data', wp_slash($fixed_elem));
    echo "  - Updated _elementor_data for ID {$p->ID}!\n";
  }
}
