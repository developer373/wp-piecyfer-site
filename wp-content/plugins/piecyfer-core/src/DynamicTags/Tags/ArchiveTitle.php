<?php
/**
 * Replacement for Elementor Pro's `archive-title` dynamic tag.
 *
 * 3 uses: the Case Studies and Blog archive templates, plus one plain
 * `heading.title`.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\DynamicTags\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

defined( 'ABSPATH' ) || exit;

final class ArchiveTitle extends Tag {

	public function get_name() {
		return 'archive-title';
	}

	public function get_title() {
		return esc_html__( 'Archive Title', 'piecyfer-core' );
	}

	public function get_group() {
		return 'archive';
	}

	public function get_categories() {
		return array( Module::TEXT_CATEGORY );
	}

	protected function register_controls() {
		$this->add_control(
			'include_context',
			array(
				'label'   => esc_html__( 'Include Context', 'piecyfer-core' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);
	}

	public function render() {
		echo wp_kses_post( $this->get_archive_title( 'yes' === $this->get_settings( 'include_context' ) ) );
	}

	/**
	 * Pro calls its own `Utils::get_page_title()`, which is unavailable here.
	 *
	 * WordPress's `get_the_archive_title()` is the natural equivalent, but it
	 * always prefixes the context ("Category: News"), whereas Pro's
	 * `include_context` switch controls exactly that prefix. So the prefix is
	 * stripped when the switch is off, using WordPress's own
	 * `get_the_archive_title_prefix()` rather than a guessed separator.
	 */
	private function get_archive_title( bool $include_context ): string {
		if ( is_search() ) {
			return sprintf(
				/* translators: %s: search query */
				esc_html__( 'Search Results for: %s', 'piecyfer-core' ),
				get_search_query()
			);
		}

		if ( is_404() ) {
			return esc_html__( 'Page Not Found', 'piecyfer-core' );
		}

		if ( ! is_archive() && ! is_home() ) {
			return (string) get_the_title();
		}

		if ( is_home() && ! is_front_page() ) {
			$blog_page = (int) get_option( 'page_for_posts' );
			return $blog_page ? (string) get_the_title( $blog_page ) : (string) get_bloginfo( 'name' );
		}

		$title = (string) get_the_archive_title();

		if ( ! $include_context ) {
			$prefix = function_exists( 'get_the_archive_title_prefix' )
				? (string) get_the_archive_title_prefix()
				: '';

			if ( '' !== $prefix && str_starts_with( wp_strip_all_tags( $title ), wp_strip_all_tags( $prefix ) ) ) {
				$title = trim( substr( wp_strip_all_tags( $title ), strlen( wp_strip_all_tags( $prefix ) ) ) );
			} else {
				// Older WordPress builds render the prefix inline as
				// "<span class="…-prefix">Category: </span>Title".
				$title = trim( preg_replace( '#<span class="[^"]*archive-(title-)?prefix"[^>]*>.*?</span>#s', '', $title ) ?? $title );
			}
		}

		return $title;
	}
}
