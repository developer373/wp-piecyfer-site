<?php
require_once __DIR__ . '/../../wp-load.php';

echo "Flushing caches and regenerating CSS...\n";

// Clear Elementor CSS cache
if (class_exists('\Elementor\Plugin')) {
  \Elementor\Plugin::$instance->files_manager->clear_cache();
  echo "Elementor CSS cache cleared.\n";
}

// Clear WP Object cache
wp_cache_flush();
echo "WordPress object cache flushed.\n";
