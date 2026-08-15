<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;
$posts = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE '%eefe266%'");
foreach ($posts as $p) {
    echo "Found in post: " . $p->post_id . "\n";
    $data = json_decode($p->meta_value, true);
    function find_el($elements, $id) {
        foreach ($elements as $el) {
            if (($el['id'] ?? '') === $id) return $el;
            if (!empty($el['elements'])) {
                $res = find_el($el['elements'], $id);
                if ($res) return $res;
            }
        }
        return null;
    }
    $el = find_el($data, 'eefe266');
    print_r($el);
}
