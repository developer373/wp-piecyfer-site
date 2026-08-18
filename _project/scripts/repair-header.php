<?php
/**
 * Standalone Theme Builder & Header Repair Script
 * Visit: https://yourdomain.com/repair-header.php
 */
define('WP_USE_THEMES', false);
require_once __DIR__ . '/wp-load.php';

global $wpdb;

echo "<pre style='font-family:monospace; background:#111; color:#0f0; padding:20px; border-radius:8px; line-height:1.5;'>";
echo "<h2>=== PIECYFER THEME BUILDER & HEADER DIAGNOSTIC / REPAIR ===</h2>\n";

// 1. Check all elementor_library posts
echo "<h3>1. Header / Footer Posts in Database:</h3>";
$templates = $wpdb->get_results("
    SELECT p.ID, p.post_title, p.post_status, p.post_type,
           MAX(CASE WHEN pm.meta_key = '_elementor_template_type' THEN pm.meta_value END) as template_type
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'elementor_library'
    GROUP BY p.ID
    ORDER BY p.ID ASC
");

$header_id = 0;
foreach ($templates as $t) {
    echo "ID: {$t->ID} | Status: [{$t->post_status}] | Type: [{$t->template_type}] | Title: '{$t->post_title}'\n";
    if ($t->template_type === 'header' || stripos($t->post_title, 'header') !== false) {
        $header_id = $t->ID;
    }
}

if (!$header_id) {
    $header_id = 171;
    echo "\n<strong style='color:#ff0;'>Notice: Header template type not detected, defaulting to ID 171.</strong>\n";
}

// 2. Ensure Header & Footer are Published & Typed
echo "\n<h3>2. Repairing Post Metadata for Header [ID: {$header_id}]:</h3>";
$wpdb->update($wpdb->posts, ['post_status' => 'publish'], ['ID' => $header_id]);
update_post_meta($header_id, '_elementor_template_type', 'header');
update_post_meta($header_id, '_elementor_edit_mode', 'builder');
update_post_meta($header_id, '_elementor_conditions', ['include/general']);
echo "Header ID {$header_id} status updated to 'publish', meta set to 'header' & 'include/general'.\n";

if ($wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE ID = 1273")) {
    $wpdb->update($wpdb->posts, ['post_status' => 'publish'], ['ID' => 1273]);
    update_post_meta(1273, '_elementor_template_type', 'footer');
    update_post_meta(1273, '_elementor_conditions', ['include/general']);
    echo "Footer ID 1273 verified.\n";
}

// 3. Rebuild elementor_pro_theme_builder_conditions
echo "\n<h3>3. Rebuilding 'elementor_pro_theme_builder_conditions' Option:</h3>";
$conditions = [
    'header' => [
        (int)$header_id => ['include/general'],
    ],
    'footer' => [
        991509 => [
            'include/singular/page/1648',
            'include/singular/page/1646',
            'include/singular/page/1298',
            'include/singular/child_of/1298'
        ],
        1273 => [
            'include/general',
            'exclude/singular/page/1298'
        ]
    ],
    'single' => [
        8716 => ['include/singular/not_found404'],
        8502 => ['include/singular/post']
    ],
    'archive' => [
        8711 => ['include/archive/search'],
        8559 => ['include/archive', 'exclude/archive/search']
    ]
];

update_option('elementor_pro_theme_builder_conditions', $conditions);
echo "Saved Conditions Array:\n";
print_r(get_option('elementor_pro_theme_builder_conditions'));

// 4. Flush Elementor and Object Caches
echo "\n<h3>4. Flushing Caches:</h3>";
if (isset(\Elementor\Plugin::$instance->files_manager)) {
    \Elementor\Plugin::$instance->files_manager->clear_cache();
    echo "Elementor CSS cache cleared.\n";
}
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
    echo "Object cache flushed.\n";
}

echo "\n<h2 style='color:#0ff;'>=== REPAIR COMPLETED SUCCESSFULLY! ===</h2>";
echo "Now visit your homepage in incognito mode (or Ctrl+F5) to see the Header!";
echo "</pre>";
