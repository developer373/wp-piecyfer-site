<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../wp-load.php';
global $wpdb;

$posts = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE '%elementskit%'");
echo "Posts with elementskit widgets count: " . count($posts) . "\n";
$found_types = [];

function scan_types($els, &$found) {
    if (!is_array($els)) return;
    foreach ($els as $el) {
        $t = $el['widgetType'] ?? ($el['elType'] ?? '');
        if (strpos($t, 'elementskit') !== false || strpos($t, 'ekit') !== false) {
            $found[$t] = ($found[$t] ?? 0) + 1;
        }
        if (!empty($el['elements'])) scan_types($el['elements'], $found);
    }
}

foreach ($posts as $p) {
    $data = json_decode($p->meta_value, true);
    scan_types($data, $found_types);
}
print_r($found_types);
