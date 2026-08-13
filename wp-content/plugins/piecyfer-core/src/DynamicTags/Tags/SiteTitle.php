<?php
/**
 * Replacement for Elementor Pro's `site-title` dynamic tag.
 *
 * 2 uses, both on plain `heading.title` widgets.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\DynamicTags\Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

defined( 'ABSPATH' ) || exit;

final class SiteTitle extends Tag {

	public function get_name() {
		return 'site-title';
	}

	public function get_title() {
		return esc_html__( 'Site Title', 'piecyfer-core' );
	}

	public function get_group() {
		return 'site';
	}

	public function get_categories() {
		return array( Module::TEXT_CATEGORY );
	}

	public function render() {
		echo wp_kses_post( get_bloginfo( 'name' ) );
	}
}
