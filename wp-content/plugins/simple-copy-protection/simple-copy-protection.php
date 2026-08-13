<?php
/*
Plugin Name: Simple Copy Protection
Description: Prevents right-click and text selection to protect content.
Version: 1.0
Author: Haris Ali
Author URI: https://harisisonline.com/
*/

function scp_disable_copy_protection() {
    echo "
    <style>
        body {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
    </style>
    <script type='text/javascript'>
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
        });
    </script>
    ";
}
add_action('wp_head', 'scp_disable_copy_protection');