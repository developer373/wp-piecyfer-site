<?php
require_once __DIR__ . '/../../wp-load.php';

$p = get_post(996324);
echo "Post Title: " . $p->post_title . "\n";
echo "Post Content has crm-solutions: " . (strpos($p->post_content, 'crm-solutions') !== false ? 'YES' : 'NO') . "\n";

$elem = get_post_meta(996324, '_elementor_data', true);
echo "Elementor Data has crm-solutions: " . (strpos($elem, 'crm-solutions') !== false ? 'YES' : 'NO') . "\n";

if (strpos($p->post_content, 'crm-solutions') !== false) {
  $fixed = str_replace('href="/crm-solutions"', 'href="' . home_url('/crm-solutions/') . '"', $p->post_content);
  $fixed = str_replace('href="/crm-solutions/"', 'href="' . home_url('/crm-solutions/') . '"', $fixed);
  wp_update_post([
    'ID' => 996324,
    'post_content' => $fixed
  ]);
  echo "Fixed in post_content!\n";
}

if ($elem && strpos($elem, 'crm-solutions') !== false) {
  $fixed_elem = str_replace('"url":"\/crm-solutions"', '"url":"' . str_replace('/', '\/', home_url('/crm-solutions/')) . '"', $elem);
  $fixed_elem = str_replace('"url":"\/crm-solutions\/"', '"url":"' . str_replace('/', '\/', home_url('/crm-solutions/')) . '"', $fixed_elem);
  update_post_meta(996324, '_elementor_data', wp_slash($fixed_elem));
  echo "Fixed in _elementor_data!\n";
}
