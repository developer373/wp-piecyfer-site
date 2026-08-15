<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../wp-load.php';
global $wpdb;

echo "=== POSTS ===\n";
$posts = $wpdb->get_results("SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_content LIKE '%Simplify button structure%'");
print_r($posts);

echo "=== POSTMETA ===\n";
$meta = $wpdb->get_results("SELECT post_id, meta_key FROM {$wpdb->postmeta} WHERE meta_value LIKE '%Simplify button structure%'");
print_r($meta);

echo "=== OPTIONS ===\n";
$opts = $wpdb->get_results("SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE '%Simplify button structure%'");
print_r($opts);
