<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../wp-load.php';
global $wpdb;

$experiments = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'elementor_experiment-%' OR option_name LIKE 'elementor_%'");
foreach ($experiments as $e) {
    if (strpos($e->option_name, 'elementor_experiment-') === 0 || in_array($e->option_name, ['elementor_css_print_method', 'elementor_load_fa4_shim'])) {
        echo sprintf("%-40s : %s\n", $e->option_name, $e->option_value);
    }
}
