<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../wp-load.php';

$plugin = $argv[1] ?? '';
if (!$plugin) {
    echo "Usage: php deactivate-plugin.php <plugin-basename>\n";
    exit(1);
}

$active = get_option('active_plugins', []);
$found = false;
$new_active = [];
foreach ($active as $p) {
    if ($p === $plugin || strpos($p, $plugin) === 0) {
        $found = true;
        echo "Deactivating: $p\n";
    } else {
        $new_active[] = $p;
    }
}

if ($found) {
    update_option('active_plugins', $new_active);
    echo "Active plugins updated (" . count($active) . " -> " . count($new_active) . ")\n";
} else {
    echo "Plugin '$plugin' was not active.\n";
}
