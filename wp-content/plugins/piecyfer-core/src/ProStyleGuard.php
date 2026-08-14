<?php
/**
 * Removes Elementor Pro stylesheets for widgets PieCyfer has taken over.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core;

defined( 'ABSPATH' ) || exit;

/**
 * While Pro is still installed, both stylesheets load: Pro's, because something
 * in its stack still enqueues it, and ours, because our widget asks for it.
 *
 * That is worse than untidy — it makes the verification dishonest. A comparison
 * that passes with Pro's CSS still on the page proves only "our stylesheet does
 * not break anything", not "our stylesheet is sufficient". The day Pro is
 * removed, any rule we forgot to write would surface all at once, on every page,
 * with no baseline left to diff against.
 *
 * Dequeuing Pro's stylesheet for each widget we own makes the test real: from
 * here on, a passing comparison means our CSS carries the widget by itself.
 * It is also simply correct — two stylesheets for one widget is dead weight.
 */
final class ProStyleGuard {

	/**
	 * Elementor Pro style handles to drop, keyed by the widget we replaced.
	 *
	 * Only add a handle once the corresponding widget is registered by us, or
	 * the page loses styling with nothing to replace it.
	 *
	 * @var array<string,string> our widget name => Pro style handle
	 */
	private const SUPERSEDED = array(
		'blockquote'           => 'widget-blockquote',
		'search-form'          => 'widget-search-form',
		'call-to-action'       => 'widget-call-to-action',
		'gallery'              => 'widget-gallery',
		'testimonial-carousel' => 'widget-testimonial-carousel',
		'nav-menu'             => 'widget-nav-menu',
		'form'                 => 'widget-form',
		/*
		 * Not a widget name — a second stylesheet the carousel widgets share, so
		 * it is listed against the widget that pulls it in. Pro registers it as
		 * its own handle, and Elementor enqueues it independently, so it has to
		 * be suppressed independently too.
		 */
		'testimonial-carousel/module-base' => 'widget-carousel-module-base',
	);

	/*
	 * `widget-post-info` is deliberately absent. It was never enqueued once our
	 * post-info widget took over — Elementor enqueues a widget's stylesheet from
	 * the rendering widget's own get_style_depends(), and ours does not ask for
	 * Pro's. The capture proved it: `widget-post-info-css` left the page without
	 * any help from this class. Listing it anyway would be dead configuration
	 * that implies a dependency we do not have.
	 *
	 * Blockquote and search-form DO need suppressing, because something else in
	 * Pro's stack enqueues those two regardless of what renders.
	 */

	public static function init(): void {
		/*
		 * Filtering the printed tag rather than dequeuing.
		 *
		 * Dequeuing on wp_enqueue_scripts and wp_print_styles was tried first
		 * and did not work: Elementor enqueues a widget's stylesheet while
		 * rendering that widget, which is after both hooks have run. The
		 * style_loader_tag filter fires as each <link> is written, so it catches
		 * the handle no matter when it was enqueued.
		 */
		add_filter( 'style_loader_tag', array( self::class, 'filter_tag' ), 10, 2 );
	}

	/**
	 * Suppress the <link> for any superseded handle.
	 *
	 * @param string $tag    The full <link> markup.
	 * @param string $handle The style's handle.
	 */
	public static function filter_tag( $tag, $handle ) {
		if ( is_admin() ) {
			return $tag;
		}
		return self::is_superseded( (string) $handle ) ? '' : $tag;
	}

	/**
	 * Whether a given Pro handle is superseded — used by the diagnostics script.
	 */
	public static function is_superseded( string $handle ): bool {
		return in_array( $handle, self::SUPERSEDED, true );
	}
}
