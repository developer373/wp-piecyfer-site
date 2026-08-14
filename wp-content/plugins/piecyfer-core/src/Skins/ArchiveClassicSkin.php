<?php
/**
 * Port of `ElementorPro\Modules\ThemeBuilder\Skins\Posts_Archive_Skin_Classic`
 * (elementor-pro/modules/theme-builder/skins/posts-archive-skin-classic.php).
 *
 * Two things in this 33-line class shape everything the `archive-posts` widget
 * does, and both look like bugs:
 *
 *   1. `_register_controls_actions()` does **not** call `parent::`, so the
 *      classic skin's `classic_section_design_layout/after_section_end` hook is
 *      never added — which is why Pro's archive skins have no Box section at
 *      all, and why VamTam re-implements one from `archive-posts.php`.
 *   2. `get_container_class()` hard-codes `elementor-posts--skin-classic`
 *      instead of deriving it from `get_id()`. `VamtamArchiveClassicSkin`
 *      inherits it, which is why the SAME skin id renders
 *      `elementor-posts--skin-vamtam_classic` on /blogs/ and
 *      `elementor-posts--skin-classic` on /?s=software. Verified against
 *      _project/snapshots/ref-a/html/. Do not "fix" either one.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Skins;

defined( 'ABSPATH' ) || exit;

class ArchiveClassicSkin extends ClassicSkin {

	use ArchiveSkinRenderTrait;

	protected function _register_controls_actions() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		add_action( 'elementor/element/archive-posts/section_layout/before_section_end', [ $this, 'register_controls' ] );
		add_action( 'elementor/element/archive-posts/section_layout/after_section_end', [ $this, 'register_style_sections' ] );
	}

	public function get_id() {
		return 'archive_classic';
	}

	public function get_title() {
		return esc_html__( 'Classic', 'piecyfer-core' );
	}

	public function get_container_class() {
		// Use parent class and parent css. See the class docblock, point 2.
		return 'elementor-posts--skin-classic';
	}

	/* Remove `posts_per_page` control */
	protected function register_post_count_control() {}
}
