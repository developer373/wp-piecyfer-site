<?php
require_once __DIR__ . '/../../wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/update.php';

// Force check for plugin updates
wp_version_check();
wp_update_plugins();

$all_plugins = get_plugins();
$active_plugins = get_option('active_plugins', []);
$update_plugins = get_site_transient('update_plugins');

$plugin_data = [];

foreach ($all_plugins as $file => $data) {
  $is_active = in_array($file, $active_plugins);
  $has_update = isset($update_plugins->response[$file]);
  $new_version = $has_update ? $update_plugins->response[$file]->new_version : null;
  $package = $has_update && isset($update_plugins->response[$file]->package) ? $update_plugins->response[$file]->package : null;

  $plugin_data[] = [
    'file' => $file,
    'name' => $data['Name'],
    'version' => $data['Version'],
    'is_active' => $is_active,
    'author' => $data['Author'],
    'plugin_uri' => $data['PluginURI'],
    'has_update' => $has_update,
    'new_version' => $new_version,
    'package_available' => !empty($package)
  ];
}

echo json_encode($plugin_data, JSON_PRETTY_PRINT) . "\n";
