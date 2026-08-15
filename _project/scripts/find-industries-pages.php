<?php
require_once __DIR__ . '/../../wp-load.php';

$posts = get_posts([
  'post_type' => ['page', 'post', 'elementor_library'],
  'posts_per_page' => -1,
  'post_status' => 'publish'
]);

$found_pages = [];
foreach ($posts as $p) {
  $data = get_post_meta($p->ID, '_elementor_data', true);
  if (is_string($data) && (strpos($data, 'Sales.svg') !== false || strpos($data, 'Manufacturing.svg') !== false || strpos($data, 'HR.svg') !== false || strpos($data, 'Industries') !== false)) {
    $found_pages[] = [
      'ID' => $p->ID,
      'title' => $p->post_title,
      'slug' => $p->post_name,
      'type' => $p->post_type
    ];
  }
}

echo "Found in " . count($found_pages) . " pages/templates:\n";
echo json_encode($found_pages, JSON_PRETTY_PRINT) . "\n";
