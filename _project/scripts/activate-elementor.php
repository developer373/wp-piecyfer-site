<?php
require_once __DIR__ . '/../../wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

echo "Activating elementor/elementor.php...\n";
$res = activate_plugin('elementor/elementor.php');

if (is_wp_error($res)) {
  echo "Error activating Elementor: " . $res->get_error_message() . "\n";
} else {
  echo "Elementor activated successfully!\n";
}

// Clear all Elementor CSS & cache
if (class_exists('\Elementor\Plugin')) {
  \Elementor\Plugin::$instance->files_manager->clear_cache();
  echo "Flushed Elementor CSS cache.\n";
}
wp_cache_flush();
echo "Flushed WordPress object cache.\n";
