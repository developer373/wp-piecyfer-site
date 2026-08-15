<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../wp-load.php';
$js = get_option('vamtam_additional_js');
print_r($js);
