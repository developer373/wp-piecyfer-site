<?php
/**
 * Replacement for Elementor Pro's `site-logo` data tag.
 *
 * Supplies the header logo in "Header - H. IT Services" (2 instances).
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\DynamicTags\Tags;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

defined( 'ABSPATH' ) || exit;

final class SiteLogo extends Data_Tag {

	public function get_name() {
		return 'site-logo';
	}

	public function get_title() {
		return esc_html__( 'Site Logo', 'piecyfer-core' );
	}

	public function get_group() {
		return 'site';
	}

	public function get_categories() {
		return array( Module::IMAGE_CATEGORY );
	}

	/**
	 * Returns the Customizer's custom logo.
	 *
	 * Pro falls back to a placeholder image shipped in its own assets folder.
	 * Pointing at that file would reintroduce a dependency on the plugin we are
	 * removing, so an empty value is returned instead: Elementor renders
	 * nothing rather than a broken image, and a site with no logo set is a
	 * configuration problem the editor should see, not one to paper over.
	 *
	 * @param array $options Unused; part of the base signature.
	 * @return array{id:int|string,url:string}
	 */
	public function get_value( array $options = array() ) {
		$logo_id = (int) get_theme_mod( 'custom_logo' );

		if ( ! $logo_id ) {
			return array(
				'id'  => '',
				'url' => '',
			);
		}

		$src = wp_get_attachment_image_src( $logo_id, 'full' );

		return array(
			'id'  => $logo_id,
			'url' => is_array( $src ) ? $src[0] : '',
		);
	}
}
