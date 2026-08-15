<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../wp-load.php';

$opts = [
    'elementor_experiment-e_optimized_css_loading' => 'active',
    'elementor_experiment-e_optimized_assets_loading' => 'active',
    'elementor_experiment-e_font_icon_svg' => 'active',
    'elementor_experiment-e_lazyload' => 'active',
    'elementor_css_print_method' => 'external',
];

foreach ($opts as $k => $v) {
    update_option($k, $v);
    echo "Set $k = $v\n";
}

// Clear Elementor CSS cache
if (class_exists('\Elementor\Plugin')) {
    \Elementor\Plugin::$instance->files_manager->clear_cache();
    echo "Elementor files cache cleared.\n";
}
