<?php
/**
 * piecyfer-theme bootstrap.
 *
 * Everything this theme does is in inc/. Nothing runs at include time except
 * the constants below and the requires; every behaviour is attached to a hook
 * inside its own file, so a `grep add_action inc/` is a complete inventory of
 * the theme's side effects.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

define( 'PIECYFER_THEME_VERSION', '1.0.0' );
define( 'PIECYFER_THEME_DIR', trailingslashit( get_template_directory() ) );
define( 'PIECYFER_THEME_URI', trailingslashit( get_template_directory_uri() ) );

require_once PIECYFER_THEME_DIR . 'inc/setup.php';
require_once PIECYFER_THEME_DIR . 'inc/locations.php';
require_once PIECYFER_THEME_DIR . 'inc/tokens.php';
require_once PIECYFER_THEME_DIR . 'inc/enqueue.php';
require_once PIECYFER_THEME_DIR . 'inc/head.php';
require_once PIECYFER_THEME_DIR . 'inc/template.php';
require_once PIECYFER_THEME_DIR . 'inc/compat.php';
