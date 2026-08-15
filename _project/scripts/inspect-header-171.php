<?php
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

// Check Post ID 171
$data = get_post_meta(171, '_elementor_data', true);
if ($data) {
  echo "Found Elementor data in Post 171, length: " . strlen($data) . "\n";
  // Search for any URLs in 171
  if (preg_match_all('#https?://[^\s"\'<>]+|/[a-zA-Z0-9_\-\#][^\s"\'<>]*#', $data, $matches)) {
    $unique = array_unique($matches[0]);
    echo "URLs in Header 171:\n";
    foreach ($unique as $u) {
      if (strpos($u, 'localhost') !== false || strpos($u, '/') === 0 || strpos($u, 'piecyfer') !== false) {
        echo "  - $u\n";
      }
    }
  }
}
