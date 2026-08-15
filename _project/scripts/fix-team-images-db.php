<?php
require_once __DIR__ . '/../../wp-load.php';

$page_id = 1295;
$data = get_post_meta($page_id, '_elementor_data', true);
if (is_string($data)) {
  $data = json_decode($data, true);
}

function update_image_urls(&$elements) {
  if (!is_array($elements)) return;
  foreach ($elements as &$el) {
    if (!empty($el['settings']['html'])) {
      $el['settings']['html'] = str_replace(
        ['src="/wp-content/uploads/', 'src="http://localhost/wp-content/uploads/'],
        'src="http://localhost/piecyfer/wp-content/uploads/',
        $el['settings']['html']
      );
    }
    if (!empty($el['elements'])) {
      update_image_urls($el['elements']);
    }
  }
}

update_image_urls($data);

update_post_meta($page_id, '_elementor_data', wp_slash(json_encode($data)));
echo "Updated post 1295 _elementor_data successfully!\n";
