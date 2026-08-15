<?php
/**
 * Phase B Cutover Script
 *
 * 1. Deactivate elementor-pro
 * 2. Switch theme to piecyfer-theme
 * 3. Update wp_posts ID 2547 (post_name = 'piecyfer-theme', post_title = 'piecyfer-theme')
 * 4. Flush Elementor CSS cache
 *
 * Usage: php cutover-phase-b.php [execute|rollback]
 */

define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../wp-load.php';

global $wpdb;

$action = $argv[1] ?? 'status';

if ($action === 'status') {
    echo "=== CUTOVER STATUS ===\n";
    $active_plugins = get_option('active_plugins');
    $pro_active = in_array('elementor-pro/elementor-pro.php', $active_plugins, true);
    echo "elementor-pro active: " . ($pro_active ? "YES" : "NO") . "\n";
    echo "active theme: " . get_option('stylesheet') . "\n";
    
    $post = $wpdb->get_row("SELECT ID, post_name, post_title FROM {$wpdb->posts} WHERE ID = 2547", ARRAY_A);
    echo "post 2547 name: " . ($post['post_name'] ?? 'none') . "\n";
    
    echo "PIECYFER_THEME_BUILDER: " . (defined('PIECYFER_THEME_BUILDER') && PIECYFER_THEME_BUILDER ? "ON" : "OFF") . "\n";
    echo "PIECYFER_CORE_FRONTEND_JS: " . (defined('PIECYFER_CORE_FRONTEND_JS') && PIECYFER_CORE_FRONTEND_JS ? "ON" : "OFF") . "\n";
    echo "PIECYFER_POPUP: " . (defined('PIECYFER_POPUP') && PIECYFER_POPUP ? "ON" : "OFF") . "\n";
    echo "PIECYFER_FORMS_ENABLED: " . (defined('PIECYFER_FORMS_ENABLED') && PIECYFER_FORMS_ENABLED ? "ON" : "OFF") . "\n";
    exit(0);
}

if ($action === 'execute') {
    echo "=== EXECUTING PHASE B CUTOVER ===\n";
    
    // 1. Deactivate elementor-pro
    $active = get_option('active_plugins');
    $before_count = count($active);
    $active = array_values(array_filter($active, function($p) {
        return strpos($p, 'elementor-pro/') !== 0;
    }));
    update_option('active_plugins', $active);
    echo "1. Deactivated elementor-pro (" . $before_count . " -> " . count($active) . " plugins)\n";
    
    // 2. Switch theme
    $old_theme = get_option('stylesheet');
    switch_theme('piecyfer-theme');
    echo "2. Switched theme: $old_theme -> " . get_option('stylesheet') . "\n";
    
    // 3. Rename post 2547 and sync theme mods
    $wpdb->update(
        $wpdb->posts,
        ['post_name' => 'piecyfer-theme', 'post_title' => 'piecyfer-theme'],
        ['ID' => 2547]
    );
    $mods = get_option('theme_mods_tecnologia', []);
    $mods['custom_css_post_id'] = 2547;
    $mods['custom_logo'] = 987718;
    update_option('theme_mods_piecyfer-theme', $mods);
    echo "3. Renamed post 2547 to 'piecyfer-theme' and synced theme_mods_piecyfer-theme\n";
    
    // 4. Flush Elementor CSS cache
    if (isset(\Elementor\Plugin::$instance->files_manager)) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
    echo "4. Flushed Elementor CSS cache\n";
    echo "=== CUTOVER EXECUTION FINISHED ===\n";
    exit(0);
}

if ($action === 'rollback') {
    echo "=== ROLLING BACK PHASE B CUTOVER ===\n";
    
    // 1. Activate elementor-pro
    $active = get_option('active_plugins');
    if (!in_array('elementor-pro/elementor-pro.php', $active, true)) {
        $active[] = 'elementor-pro/elementor-pro.php';
        update_option('active_plugins', $active);
    }
    echo "1. Re-activated elementor-pro\n";
    
    // 2. Switch theme back
    switch_theme('tecnologia');
    echo "2. Switched theme back to tecnologia\n";
    
    // 3. Rename post 2547 back
    $wpdb->update(
        $wpdb->posts,
        ['post_name' => 'tecnologia', 'post_title' => 'tecnologia'],
        ['ID' => 2547]
    );
    echo "3. Renamed post 2547 post_name back to 'tecnologia'\n";
    
    // 4. Flush Elementor CSS cache
    if (isset(\Elementor\Plugin::$instance->files_manager)) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
    echo "4. Flushed Elementor CSS cache\n";
    echo "=== ROLLBACK FINISHED ===\n";
    exit(0);
}
