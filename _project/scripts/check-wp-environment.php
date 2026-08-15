<?php
require_once __DIR__ . '/../../wp-load.php';
global $wp_version;

echo "WordPress Version: " . $wp_version . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Active Theme: " . get_stylesheet() . " (Template: " . get_template() . ")\n";

$updates = get_site_transient('update_core');
if (!empty($updates->updates)) {
  foreach ($updates->updates as $u) {
    echo "Core Update Available: " . $u->response . " -> " . $u->version . "\n";
  }
} else {
  echo "Core is up to date.\n";
}
