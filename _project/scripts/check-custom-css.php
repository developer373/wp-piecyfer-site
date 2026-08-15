<?php
require_once __DIR__ . '/../../wp-load.php';
$css = wp_get_custom_css();
echo "CUSTOM CSS (length: " . strlen($css) . "):\n";
echo $css . "\n";
