<?php
/**
 * Replacement for Elementor Pro's `theme-archive-title` widget.
 *
 * 2 instances: the Case Studies and Blog Posts archive templates.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

defined( 'ABSPATH' ) || exit;

final class ArchiveTitleWidget extends TitleWidgetBase {

	public function get_name(): string {
		return 'theme-archive-title';
	}

	public function get_title(): string {
		return esc_html__( 'Archive Title', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-archive-title';
	}

	public function get_keywords(): array {
		return array( 'title', 'heading', 'archive' );
	}

	protected function get_dynamic_tag_name(): string {
		return 'archive-title';
	}
}
