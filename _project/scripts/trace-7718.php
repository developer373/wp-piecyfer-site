<?php
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/piecyfer/';
require_once __DIR__ . '/../../wp-load.php';

wp();
ob_start();
wp_head();
ob_end_clean();

global $wp_styles;
echo "ALL ENQUEUED HANDLES (" . count($wp_styles->queue) . "):\n";
foreach ( $wp_styles->queue as $h ) {
    $src = $wp_styles->registered[ $h ]->src ?? '(inline/no-src)';
    echo " - {$h} : {$src}\n";
}
