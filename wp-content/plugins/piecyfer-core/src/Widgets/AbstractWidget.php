<?php
/**
 * Shared base for every PieCyfer replacement widget.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * The contract every replacement widget inherits.
 *
 * A replacement widget is not a new widget. It is a drop-in for one that
 * already exists in the saved page data, so three things are load-bearing and
 * must never drift:
 *
 *   1. get_name() must return the exact `widgetType` string stored in
 *      `_elementor_data`. That string is the join key between the saved JSON
 *      and the class that renders it. Change it and every existing instance
 *      orphans into an "unavailable widget" placeholder.
 *
 *   2. Control ids must match one-for-one, including responsive suffixes
 *      (_tablet, _mobile) and the __globals__ / __dynamic__ maps. Elementor
 *      drops any saved value that has no matching control the next time the
 *      document is saved — silent, irreversible data loss.
 *
 *   3. Rendered markup and CSS class names must match, because the theme
 *      stylesheet targets Elementor's own class names. New class names break
 *      styling in a way screenshots catch but HTML diffs explain.
 *
 * Verify all three with `node _project/pixel-tool/compare.js baseline <step>`.
 */
abstract class AbstractWidget extends Widget_Base {

	/**
	 * Widget handles whose assets are already on the page this request, so a
	 * page with twelve icon-boxes enqueues the icon-box stylesheet once.
	 *
	 * @var array<string,true>
	 */
	private static array $enqueued = array();

	/**
	 * The plugin whose widget this replaces. Documentation only — it shows up
	 * in the editor panel so it is obvious what a given widget stands in for.
	 */
	protected function replaces(): string {
		return '';
	}

	/**
	 * Widgets keep the category their saved instances already reference, so
	 * they appear where the content expects them.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( 'general' );
	}

	/**
	 * Per-widget assets, enqueued only on pages where the widget is present.
	 *
	 * Elementor Pro ships one large stylesheet regardless of what a page uses.
	 * Owning this code lets us do the opposite, which is a real page-weight win
	 * and costs nothing to implement here.
	 *
	 * Subclasses override assets() rather than this method.
	 */
	final protected function enqueue_assets(): void {
		$handle = 'piecyfer-' . $this->get_name();
		if ( isset( self::$enqueued[ $handle ] ) ) {
			return;
		}
		self::$enqueued[ $handle ] = true;
		$this->assets( $handle );
	}

	/**
	 * Register and enqueue this widget's CSS/JS. Default: nothing.
	 *
	 * @param string $handle Stable handle derived from the widget name.
	 */
	protected function assets( string $handle ): void {
		// Most widgets are styled entirely by Elementor's generated CSS.
	}

	/**
	 * Render, with a guard so one broken widget cannot blank the whole page.
	 *
	 * Subclasses implement render_widget(); render() stays final so the guard
	 * cannot be bypassed by accident.
	 */
	final protected function render(): void {
		try {
			$this->enqueue_assets();
			$this->render_widget();
		} catch ( \Throwable $e ) {
			$this->render_failure( $e );
		}
	}

	/**
	 * The actual output. Must emit the same markup as the widget being replaced.
	 */
	abstract protected function render_widget(): void;

	/**
	 * What a visitor sees when a widget throws: nothing.
	 *
	 * Editors and administrators get a visible marker instead, because a
	 * silently missing section is worse than an obvious one for whoever has to
	 * fix it. Either way the page keeps rendering.
	 */
	private function render_failure( \Throwable $e ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf( '[piecyfer-core] %s render failed: %s in %s:%d', $this->get_name(), $e->getMessage(), $e->getFile(), $e->getLine() )
			);
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		printf(
			'<div class="piecyfer-widget-error" style="padding:12px;border:1px dashed #c00;color:#c00;font:13px/1.4 system-ui,sans-serif">%s</div>',
			sprintf(
				/* translators: 1: widget name, 2: error message */
				esc_html__( 'PieCyfer widget "%1$s" failed to render: %2$s', 'piecyfer-core' ),
				esc_html( $this->get_name() ),
				esc_html( $e->getMessage() )
			)
		);
	}
}
