<?php
require_once __DIR__ . '/../../wp-load.php';

$posts = get_posts([
  'post_type' => ['page', 'post'],
  'posts_per_page' => -1,
  'post_status' => 'publish'
]);

$embedding_pages = [];
foreach ($posts as $p) {
  $data = get_post_meta($p->ID, '_elementor_data', true);
  if (is_string($data) && (strpos($data, '989489') !== false || strpos($data, 'Industries') !== false)) {
    $embedding_pages[] = [
      'ID' => $p->ID,
      'title' => $p->post_title,
      'slug' => $p->post_name,
      'url' => get_permalink($p->ID)
    ];
  }
}

echo "Pages embedding Industries template:\n";
echo json_encode($embedding_pages, JSON_PRETTY_PRINT) . "\n";
