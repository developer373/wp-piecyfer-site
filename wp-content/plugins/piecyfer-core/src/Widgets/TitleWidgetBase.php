<?php
/**
 * Shared base for the theme-builder title widgets.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Plugin as ElementorPlugin;
use Elementor\Widget_Heading;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor Pro's `theme-post-title` and `theme-archive-title` are not bespoke
 * widgets — they are the *free* Heading widget with three changes:
 *
 *   1. `title` defaults to a dynamic tag (post-title / archive-title)
 *   2. `header_size` defaults to h1
 *   3. two extra wrapper classes, `elementor-page-title elementor-widget-heading`
 *
 * Reproducing them therefore means extending Widget_Heading and applying the
 * same three changes, which also guarantees every one of Heading's ~40 style
 * controls keeps its exact id. Rebuilding them by hand would have been both
 * more work and less faithful.
 *
 * Note this class does NOT extend AbstractWidget: that one derives from
 * Widget_Base and makes render() final, which is the wrong shape here. The
 * render guard is applied locally instead.
 */
abstract class TitleWidgetBase extends Widget_Heading {

	/** The dynamic tag supplying this widget's default content. */
	abstract protected function get_dynamic_tag_name(): string;

	public function get_categories(): array {
		return array( 'pro-elements' );
	}

	protected function register_controls(): void {
		parent::register_controls();

		$tags = ElementorPlugin::$instance->dynamic_tags;

		$this->update_control(
			'title',
			array(
				'dynamic' => array(
					'default' => $tags->tag_data_to_tag_text( null, $this->get_dynamic_tag_name() ),
				),
			),
			array( 'recursive' => true )
		);

		$this->update_control(
			'header_size',
			array( 'default' => 'h1' )
		);
	}

	/**
	 * Matches Pro exactly. `parent::get_name()` is `heading`, so the wrapper
	 * still carries `elementor-widget-heading` — which the theme stylesheet
	 * targets. Dropping it would restyle every page title invisibly.
	 */
	protected function get_html_wrapper_class(): string {
		return parent::get_html_wrapper_class() . ' elementor-page-title elementor-widget-' . parent::get_name();
	}

	/**
	 * A document can opt out of showing its own title. Pro honours that here,
	 * and so must we, or a page that hid its title would suddenly show one.
	 */
	protected function should_show_page_title(): bool {
		$document = ElementorPlugin::$instance->documents->get( get_the_ID() );

		if ( $document && 'yes' === $document->get_settings( 'hide_title' ) ) {
			return false;
		}

		return true;
	}

	protected function render(): void {
		if ( ! $this->should_show_page_title() ) {
			return;
		}

		try {
			parent::render();
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[piecyfer-core] ' . $this->get_name() . ' render failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}
}
