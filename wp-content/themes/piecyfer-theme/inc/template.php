<?php
/**
 * Template helpers: body classes, and the handful of questions the templates
 * need answered about what Elementor is going to render.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Body classes.
 *
 * Twelve classes, and only four are read by any stylesheet the site serves:
 * `elementor-active` (6 rules), `responsive-layout` (2), `vamtam-font-smoothing`
 * (1) and `vamtam-is-elementor` - and that last one appears only inside a
 * `body:not(.vamtam-is-elementor)` negation on a class that is present whenever
 * it would matter, so it can never match.
 *
 * The other eight are emitted for markup parity and nothing else. They are in
 * the captured HTML of all 39 pages; dropping them is a diff on every page that
 * has to be triaged by hand, so it belongs in its own commit, after the theme
 * swap is proven, not bundled with it.
 *
 * Order matters for the same reason. It is the order the previous theme emitted
 * them in.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function piecyfer_body_classes( $classes ) {
	$classes[] = 'full';
	$classes[] = 'header-layout-logo-menu';

	// The previous theme's `has_page_header()` returned true for everything on
	// this site except WooCommerce products and Events Calendar listings.
	$classes[] = is_404() ? 'no-page-header' : 'has-page-header';

	$classes[] = 'no-middle-header';
	$classes[] = 'responsive-layout';

	if ( is_singular() && has_post_thumbnail() ) {
		$classes[] = 'has-post-thumbnail';
	}

	if ( is_single() ) {
		$classes[] = 'single-post-one-column';
	}

	if ( piecyfer_is_built_with_elementor() ) {
		$classes[] = 'vamtam-is-elementor';
	}

	if ( class_exists( '\Elementor\Plugin' ) ) {
		$classes[] = 'elementor-active';
	}

	if ( function_exists( 'elementor_theme_do_location' ) ) {
		$classes[] = 'elementor-pro-active';
	}

	// WooCommerce is not installed, so the cart is unconditionally empty.
	$classes[] = 'vamtam-wc-cart-empty';

	if ( current_theme_supports( 'wc-product-gallery-slider' ) ) {
		$classes[] = 'wc-product-gallery-slider-active';
	}

	if ( piecyfer_font_smoothing() ) {
		$classes[] = 'vamtam-font-smoothing';
	}

	// `get_layout()` returned 'full' unconditionally whenever Theme Builder
	// locations were available, which on this site is always.
	$classes[] = 'layout-full';

	return $classes;
}
add_filter( 'body_class', 'piecyfer_body_classes' );

/**
 * Whether antialiasing is on.
 *
 * Stored as a kit setting by the companion plugin's Theme Settings tab. The
 * control is unset on this site and its default is on, which is why
 * `vamtam-font-smoothing` is on all 39 captured pages - so an absent key means
 * on, not off.
 *
 * @return bool
 */
function piecyfer_font_smoothing() {
	$kit = piecyfer_get_kit_settings();

	if ( ! array_key_exists( 'vamtam_theme_font_smoothing', $kit ) ) {
		return true;
	}

	return ! empty( $kit['vamtam_theme_font_smoothing'] );
}

/**
 * Whether the queried post is an Elementor document.
 *
 * Uses `$post` rather than `get_the_ID()` deliberately: on an archive this is
 * the first post of the loop, which is what the previous theme measured and
 * therefore what the captured body classes reflect.
 *
 * @return bool
 */
function piecyfer_is_built_with_elementor() {
	global $post;

	if ( ! class_exists( '\Elementor\Plugin' ) || empty( $post->ID ) ) {
		return false;
	}

	$document = \Elementor\Plugin::$instance->documents->get( $post->ID );

	return $document && $document->is_built_with_elementor();
}

/**
 * The Theme Builder documents that match this request for a location.
 *
 * The previous theme called
 * `ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()`
 * directly, from code on the hot path of every page and post. That is the sharp
 * edge of this whole migration: the moment anything other than Pro defines
 * `elementor_theme_do_location()`, the `function_exists()` guard in front of
 * those calls passes and the next line dereferences a Pro class that is no
 * longer there - a fatal on every request.
 *
 * So this asks a filter first. piecyfer-core answers it once it owns the Theme
 * Builder; Pro answers it until then, behind a `class_exists()` guard that
 * degrades to "no documents" instead of a fatal.
 *
 * @param string $location Location name.
 * @return array
 */
function piecyfer_documents_for_location( $location ) {
	$documents = apply_filters( 'piecyfer/theme/documents_for_location', null, $location );

	if ( is_array( $documents ) ) {
		return $documents;
	}

	if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
		return array();
	}

	$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();

	if ( ! $module || ! method_exists( $module, 'get_conditions_manager' ) ) {
		return array();
	}

	$found = $module->get_conditions_manager()->get_documents_for_location( $location );

	return is_array( $found ) ? $found : array();
}

/**
 * Whether a Theme Builder template actually matches this request.
 *
 * Distinct from `piecyfer_location_exists()`, which only asks whether the
 * location is registered. This one decides whether `page.php` and `single.php`
 * emit their own `.page-wrapper` and `<article>`: a post has a matching single
 * template so it does not, a page has none so it does.
 *
 * @param string $location Location name.
 * @return bool
 */
function piecyfer_location_has_template( $location ) {
	if ( ! function_exists( 'elementor_theme_do_location' ) ) {
		return false;
	}

	return ! empty( piecyfer_documents_for_location( $location ) );
}

/**
 * Whether some Elementor template is already going to render a page title.
 *
 * When it is, the theme's `#sub-header` is suppressed entirely - which is why
 * single posts, category archives, the author archive and the 404 have no
 * `#sub-header`, and pages and the search results do.
 *
 * @return bool
 */
function piecyfer_title_present_for_post() {
	$title_widgets = array(
		'theme-post-title',
		'theme-page-title',
		'theme-archive-title',
		'woocommerce-product-title',
	);

	foreach ( array( 'header', 'page-title-location', 'archive', 'single' ) as $location ) {
		foreach ( piecyfer_documents_for_location( $location ) as $document ) {
			if ( ! is_object( $document ) || ! method_exists( $document, 'get_elements_data' ) ) {
				continue;
			}

			if ( piecyfer_find_widget( $document->get_elements_data(), $title_widgets ) ) {
				return true;
			}
		}
	}

	// Nothing in a Theme Builder template, so check the document itself.
	if ( ! piecyfer_is_built_with_elementor() ) {
		return false;
	}

	global $post;

	$document = \Elementor\Plugin::$instance->documents->get( $post->ID );

	if ( ! $document ) {
		return false;
	}

	return piecyfer_find_widget(
		$document->get_elements_data(),
		array( 'theme-post-title', 'theme-page-title' )
	);
}

/**
 * Depth-first search of Elementor element data for any of the given widgets.
 *
 * @param array    $elements Elementor elements data.
 * @param string[] $types    Widget type names to look for.
 * @return bool
 */
function piecyfer_find_widget( $elements, array $types ) {
	if ( ! is_array( $elements ) ) {
		return false;
	}

	foreach ( $elements as $element ) {
		if ( ! empty( $element['widgetType'] ) && in_array( $element['widgetType'], $types, true ) ) {
			return true;
		}

		if ( ! empty( $element['elements'] ) && piecyfer_find_widget( $element['elements'], $types ) ) {
			return true;
		}
	}

	return false;
}

/**
 * The URL the attachment image should link to.
 *
 * In a gallery of more than one image the link walks to the next attachment and
 * wraps at the end; a lone attachment links to the file itself. Used only by
 * `attachment.php`, which cannot execute while `wp_attachment_pages_enabled` is
 * `0` - see the header of that file.
 *
 * @return string
 */
function piecyfer_next_attachment_url() {
	$post = get_post();

	if ( ! $post ) {
		return '';
	}

	$attachments = array_values(
		get_children(
			array(
				'post_parent'    => $post->post_parent,
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'order'          => 'ASC',
				'orderby'        => 'menu_order ID',
			)
		)
	);

	if ( count( $attachments ) < 2 ) {
		return (string) wp_get_attachment_url( $post->ID );
	}

	$index = 0;

	foreach ( $attachments as $key => $attachment ) {
		if ( (int) $attachment->ID === (int) $post->ID ) {
			$index = $key;
			break;
		}
	}

	++$index;

	$next = isset( $attachments[ $index ] ) ? $attachments[ $index ] : $attachments[0];

	return (string) get_attachment_link( $next->ID );
}

/**
 * The scroll-to-top button.
 *
 * Priority 5 puts it immediately after `</div><!-- / #page -->` and ahead of
 * everything else on `wp_footer`, which is where it is in the captured markup.
 * `assets/js/scroll-top.js` fades it in past the middle of the page.
 *
 * @return void
 */
function piecyfer_footer_additions() {
	get_template_part( 'template-parts/scroll-top' );
}
add_action( 'wp_footer', 'piecyfer_footer_additions', 5 );
