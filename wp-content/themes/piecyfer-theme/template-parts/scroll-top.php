<?php
/**
 * The scroll-to-top button.
 *
 * Printed on `wp_footer` priority 5, immediately after `</div><!-- / #page -->`.
 * `assets/js/scroll-top.js` fades it in past the middle of the page and handles
 * the click in the capture phase.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

?>

<div id="scroll-to-top" class="vamtam-scroll-to-top">
    <div id="scroll-to-top-text"><?php esc_html_e( 'top', 'piecyfer-theme' ); ?></div>
</div>
