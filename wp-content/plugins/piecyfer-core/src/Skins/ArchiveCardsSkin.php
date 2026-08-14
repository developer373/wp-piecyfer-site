<?php
/**
 * Port of `ElementorPro\Modules\ThemeBuilder\Skins\Posts_Archive_Skin_Cards`
 * (elementor-pro/modules/theme-builder/skins/posts-archive-skin-cards.php).
 *
 * Dead on this site; registered so the `archive-posts` skin bucket is complete
 * and the two orphaned `archive_cards_*` keys keep a matching control.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Skins;

defined( 'ABSPATH' ) || exit;

class ArchiveCardsSkin extends CardsSkin {

	use ArchiveSkinRenderTrait;

	protected function _register_controls_actions() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		add_action( 'elementor/element/archive-posts/section_layout/before_section_end', [ $this, 'register_controls' ] );
		add_action( 'elementor/element/archive-posts/section_layout/after_section_end', [ $this, 'register_style_sections' ] );
		add_action( 'elementor/element/archive-posts/archive_cards_section_design_image/before_section_end', [ $this, 'register_additional_design_image_controls' ] );
	}

	public function get_id() {
		return 'archive_cards';
	}

	public function get_title() {
		return esc_html__( 'Cards', 'piecyfer-core' );
	}

	public function get_container_class() {
		// Use parent class and parent css.
		return 'elementor-posts--skin-cards';
	}

	/* Remove `posts_per_page` control */
	protected function register_post_count_control() {}
}
