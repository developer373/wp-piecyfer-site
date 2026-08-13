<?php
/**
 * Replacement for Elementor Pro's `post-title` dynamic tag.
 *
 * Supplies the content of the Blog Post Template's `theme-post-title` widget.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\DynamicTags\Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

defined( 'ABSPATH' ) || exit;

final class PostTitle extends Tag {

	public function get_name() {
		return 'post-title';
	}

	public function get_title() {
		return esc_html__( 'Post Title', 'piecyfer-core' );
	}

	public function get_group() {
		return 'post';
	}

	public function get_categories() {
		return array( Module::TEXT_CATEGORY );
	}

	/**
	 * The saved instance carries `before`, `after` and `fallback` settings, so
	 * they are honoured here even though this site leaves all three empty —
	 * dropping them would silently discard stored values the moment someone
	 * fills one in.
	 */
	protected function register_controls() {
		$this->add_control(
			'before',
			array(
				'label' => esc_html__( 'Before', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'after',
			array(
				'label' => esc_html__( 'After', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'fallback',
			array(
				'label' => esc_html__( 'Fallback', 'piecyfer-core' ),
			)
		);
	}

	public function render() {
		$title = get_the_title();

		if ( '' === trim( (string) $title ) ) {
			$title = (string) $this->get_settings( 'fallback' );
			if ( '' === trim( $title ) ) {
				return;
			}
		} else {
			$title = $this->get_settings( 'before' ) . $title . $this->get_settings( 'after' );
		}

		echo wp_kses_post( $title );
	}
}
