<?php
/**
 * Replacement for Elementor Pro's `post-featured-image` data tag.
 *
 * The most-used tag on the site: 11 instances across `image.image` and
 * `column.background_image`.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\DynamicTags\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

defined( 'ABSPATH' ) || exit;

final class PostFeaturedImage extends Data_Tag {

	public function get_name() {
		return 'post-featured-image';
	}

	public function get_title() {
		return esc_html__( 'Featured Image', 'piecyfer-core' );
	}

	public function get_group() {
		return 'post';
	}

	public function get_categories() {
		return array( Module::IMAGE_CATEGORY, Module::MEDIA_CATEGORY );
	}

	protected function register_controls() {
		$this->add_control(
			'fallback',
			array(
				'label' => esc_html__( 'Fallback', 'piecyfer-core' ),
				'type'  => Controls_Manager::MEDIA,
			)
		);
	}

	/**
	 * @param array $options Unused; part of the base signature.
	 * @return array{id:int|string,url:string}
	 */
	public function get_value( array $options = array() ) {
		$thumbnail_id = (int) get_post_thumbnail_id();

		if ( $thumbnail_id ) {
			$src = wp_get_attachment_image_src( $thumbnail_id, 'full' );
			return array(
				'id'  => $thumbnail_id,
				'url' => is_array( $src ) ? $src[0] : '',
			);
		}

		$fallback = $this->get_settings( 'fallback' );

		return is_array( $fallback ) ? $fallback : array(
			'id'  => '',
			'url' => '',
		);
	}
}
