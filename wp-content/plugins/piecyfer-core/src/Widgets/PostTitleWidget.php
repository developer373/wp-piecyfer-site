<?php
/**
 * Replacement for Elementor Pro's `theme-post-title` widget.
 *
 * 1 instance, in the Blog Post Template.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

defined( 'ABSPATH' ) || exit;

final class PostTitleWidget extends TitleWidgetBase {

	/**
	 * The `theme-` prefix exists to avoid colliding with the dynamic tag of the
	 * same name. It is part of the saved data, so it is not ours to tidy up.
	 */
	public function get_name(): string {
		return 'theme-post-title';
	}

	public function get_title(): string {
		return esc_html__( 'Post Title', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-post-title';
	}

	public function get_keywords(): array {
		return array( 'title', 'heading', 'post' );
	}

	protected function get_dynamic_tag_name(): string {
		return 'post-title';
	}

	/**
	 * Pro defaults this widget's CSS class to `entry-title`, which themes and
	 * some plugins hook onto. Preserved.
	 */
	public function get_common_args(): array {
		return array(
			'_css_classes' => array(
				'default' => 'entry-title',
			),
		);
	}
}
