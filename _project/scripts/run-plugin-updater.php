<?php
require_once __DIR__ . '/../../wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

// Disable timeout limits
set_time_limit(600);

class Cli_Plugin_Upgrader_Skin extends WP_Upgrader_Skin {
  public function feedback($string, ...$args) {
    if (isset($this->upgrader->strings[$string])) {
      $string = $this->upgrader->strings[$string];
    }
    if (strpos($string, '%') !== false) {
      $string = vsprintf($string, $args);
    }
    if (empty($string)) return;
    echo "  [UPGRADER] " . strip_tags($string) . "\n";
  }

  public function header() {}
  public function footer() {}
  public function error($errors) {
    if (is_wp_error($errors)) {
      echo "  [ERROR] " . $errors->get_error_message() . "\n";
    } else {
      echo "  [ERROR] " . print_r($errors, true) . "\n";
    }
  }
}

$target = $argv[1] ?? 'all';

echo "=== CHECKING FOR UPDATES ===\n";
wp_update_plugins();
$update_plugins = get_site_transient('update_plugins');

$plugins_to_update = [];
if ($target === 'all') {
  if (!empty($update_plugins->response)) {
    $plugins_to_update = array_keys($update_plugins->response);
  }
} else {
  // specific plugin file
  foreach ($update_plugins->response as $file => $data) {
    if (strpos($file, $target) !== false) {
      $plugins_to_update[] = $file;
    }
  }
}

if (empty($plugins_to_update)) {
  echo "No updates found for target: $target\n";
  exit(0);
}

echo "Found " . count($plugins_to_update) . " plugins to update:\n";
foreach ($plugins_to_update as $p) {
  $info = $update_plugins->response[$p];
  echo "  - $p (New Version: {$info->new_version})\n";
}

$skin = new Cli_Plugin_Upgrader_Skin();
$upgrader = new Plugin_Upgrader($skin);

foreach ($plugins_to_update as $plugin_file) {
  echo "\n>>> UPDATING $plugin_file ...\n";
  $was_active = is_plugin_active($plugin_file);
  $result = $upgrader->upgrade($plugin_file);
  
  if ($result) {
    echo ">>> Successfully updated $plugin_file!\n";
    if ($was_active) {
      echo "  Re-activating $plugin_file...\n";
      $res = activate_plugin($plugin_file);
      if (is_wp_error($res)) {
        echo "  [ERROR] Re-activation failed: " . $res->get_error_message() . "\n";
      } else {
        echo "  Activated successfully!\n";
      }
    }
  } else {
    echo ">>> Failed to update $plugin_file.\n";
  }
}

// Clear all Elementor CSS & cache
if (class_exists('\Elementor\Plugin')) {
  \Elementor\Plugin::$instance->files_manager->clear_cache();
  echo "\nFlushed Elementor CSS cache.\n";
}
wp_cache_flush();
echo "Flushed WordPress object cache.\n";
