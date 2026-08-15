<?php
require_once __DIR__ . '/../../wp-load.php';

$page = get_page_by_path('our-team');
if ($page) {
  echo "Our Team Page ID: " . $page->ID . "\n";
  $data = get_post_meta($page->ID, '_elementor_data', true);
  if (is_string($data)) {
    $data_arr = json_decode($data, true);
  } else {
    $data_arr = $data;
  }
  
  // Search for html widget with ceoKamran
  function find_html_widgets($elements, &$found = []) {
    if (!is_array($elements)) return;
    foreach ($elements as $el) {
      if (($el['widgetType'] ?? '') === 'html' || ($el['elType'] ?? '') === 'widget') {
        if (!empty($el['settings']['html'])) {
          $found[] = [
            'id' => $el['id'],
            'html' => $el['settings']['html']
          ];
        }
      }
      if (!empty($el['elements'])) {
        find_html_widgets($el['elements'], $found);
      }
    }
  }

  $found = [];
  find_html_widgets($data_arr, $found);
  echo "Found " . count($found) . " HTML widgets:\n";
  foreach ($found as $f) {
    echo "ID: " . $f['id'] . "\n";
    echo $f['html'] . "\n---\n";
  }
} else {
  echo "Page our-team not found!\n";
}
