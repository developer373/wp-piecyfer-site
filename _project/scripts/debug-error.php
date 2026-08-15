<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/piecyfer/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once __DIR__ . '/../../wp-load.php';

echo "WordPress loaded successfully!\n";

wp();
echo "wp() completed successfully!\n";

ob_start();
require_once ABSPATH . WPINC . '/template-loader.php';
$output = ob_get_clean();

echo "Page rendered! Length: " . strlen($output) . " bytes\n";
echo "First 200 chars:\n" . substr($output, 0, 200) . "\n";
