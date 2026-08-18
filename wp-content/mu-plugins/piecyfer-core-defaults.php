<?php
/**
 * Plugin Name: PieCyfer Core Feature Defaults
 * Description: Ensures Theme Builder, Header/Footer, Forms, Popups, and Shortcodes are always active out-of-the-box on every deployment and environment.
 * Version: 1.0.0
 * Author: PieCyfer
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'piecyfer/theme_builder/enabled', '__return_true', 999 );
add_filter( 'piecyfer_core/frontend_js/enabled', '__return_true', 999 );
add_filter( 'piecyfer/forms/enabled', '__return_true', 999 );
add_filter( 'piecyfer/popup/enabled', '__return_true', 999 );
