<?php
/**
 * Shared base for every document that renders at the `single` location.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

use PieCyfer\Core\ThemeBuilder\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/single-base.php`.
 *
 * This is the only place in the whole Theme Builder that touches the WordPress
 * loop, and the two lines that do are load-bearing:
 *
 *   - `the_post()` in before_get_content() is what fires `loop_start`, which a
 *     surprising number of plugins hang content off.
 *   - `wp_reset_postdata()` in after_get_content() puts the global post back,
 *     which matters because the footer renders after this.
 *
 * It is also where the long class list on a single post's wrapper comes from —
 * `post-993554 post type-post status-publish …` is `get_post_class()`, appended
 * only when `is_singular()`. A 404 is not singular, which is why template 8716
 * renders with no post classes at all.
 */
abstract class SingleBase extends ArchiveSingleBase {

	public static function get_properties() {
		$properties = parent::get_properties();

		$properties['location']       = 'single';
		$properties['condition_type'] = 'singular';

		return $properties;
	}

	public static function get_title() {
		return esc_html__( 'Single', 'piecyfer-core' );
	}

	public static function get_plural_title() {
		return esc_html__( 'Singles', 'piecyfer-core' );
	}

	public function before_get_content() {
		parent::before_get_content();

		// For the `loop_start` hook.
		if ( have_posts() ) {
			the_post();
		}
	}

	public function after_get_content() {
		wp_reset_postdata();

		parent::after_get_content();
	}

	public function get_container_attributes() {
		$attributes = parent::get_container_attributes();

		if ( is_singular() /* not 404 */ ) {
			$post_classes        = get_post_class( '', get_the_ID() );
			$attributes['class'] .= ' ' . implode( ' ', $post_classes );
		}

		return $attributes;
	}

	/**
	 * Show a placeholder instead of the content when the *queried* post is
	 * itself a theme document belonging to a different location — an editor and
	 * preview nicety that would otherwise render a header inside a single-post
	 * preview.
	 */
	public function print_content() {
		$requested_post_id = get_the_ID();

		if ( $requested_post_id !== $this->post->ID ) {
			$requested_document = Module::instance()->get_document( (int) $requested_post_id );

			if ( $requested_document
				&& ! $requested_document instanceof SectionDocument
				&& $requested_document->get_location() !== $this->get_location()
			) {
				echo '<div class="elementor-theme-builder-content-area">' . esc_html__( 'Content Area', 'piecyfer-core' ) . '</div>';

				return;
			}
		}

		parent::print_content();
	}
}
