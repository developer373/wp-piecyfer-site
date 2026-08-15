<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

echo "=== POST 2547 ===\n";
$post = $wpdb->get_row("SELECT ID, post_name, post_title, post_type, post_status FROM {$wpdb->posts} WHERE ID = 2547", ARRAY_A);
print_r($post);

echo "\n=== ACTIVE THEME ===\n";
echo "stylesheet: " . get_option('stylesheet') . "\n";
echo "template:   " . get_option('template') . "\n";

echo "\n=== ACTIVE PLUGINS ===\n";
$active = get_option('active_plugins');
print_r($active);

echo "\n=== THEMES AVAILABLE ===\n";
$themes = wp_get_themes();
foreach ($themes as $slug => $theme_obj) {
    echo " - $slug ({$theme_obj->get('Name')})\n";
}
