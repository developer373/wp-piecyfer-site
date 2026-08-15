<?php
require_once __DIR__ . '/../../wp-load.php';

$m1 = get_option('theme_mods_tecnologia');
$m2 = get_option('theme_mods_piecyfer-theme', array());

// Copy all theme mods from tecnologia to piecyfer-theme
foreach ($m1 as $k => $v) {
    $m2[$k] = $v;
}
$m2['custom_css_post_id'] = 2547;
$m2['custom_logo'] = 987718;

update_option('theme_mods_piecyfer-theme', $m2);

// Check post 2547
global $wpdb;
$post = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE ID = 2547");
echo "Post 2547:\n";
echo "  post_name: " . $post->post_name . "\n";
echo "  post_title: " . $post->post_title . "\n";
echo "  post_type: " . $post->post_type . "\n";
echo "  content length: " . strlen($post->post_content) . "\n";

// Update post_name to 'piecyfer-theme' if needed
$wpdb->update(
    $wpdb->posts,
    array(
        'post_name' => 'piecyfer-theme',
        'post_title' => 'piecyfer-theme',
    ),
    array('ID' => 2547)
);

echo "Updated theme_mods_piecyfer-theme:\n";
print_r(get_option('theme_mods_piecyfer-theme'));
