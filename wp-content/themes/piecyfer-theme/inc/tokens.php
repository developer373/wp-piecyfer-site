<?php
/**
 * Design tokens - the `:root{}` block of CSS custom properties.
 *
 * This is the load-bearing file of the theme. The four stylesheets in
 * assets/css/ read 159 distinct `--vamtam-*` custom properties and declare none
 * of them; this file is where they come from. Get one value wrong and text
 * shifts by a pixel on some pages and not others.
 *
 * WHY THE NAMES STILL SAY `--vamtam-`
 * -----------------------------------
 * Renaming the prefix means editing 159 references across four minified
 * stylesheets and getting every one right, for no functional gain, in the one
 * area of this project where a mistake is invisible until someone edits a page.
 * The prefix is now a private implementation detail of files we own. Rename it
 * mechanically later, once the pixel harness is green, or never.
 *
 * WHERE THE VALUES COME FROM
 * --------------------------
 * The active Elementor kit (post 5), which stores VamTam-specific global ids:
 * `system_colors[_id] = vamtam_accent_1..8` and `vamtam_sticky_header_bg_color`,
 * `system_typography[_id] = vamtam_primary_font, vamtam_h1..h6`. That naming is
 * what makes a mechanical translation possible at all. Plus 28 glyph codes read
 * from the Elementor custom icon set, and 14 static values.
 *
 * The Customizer contributes nothing: no option in the old theme carried
 * `compiler => true`, so the previous implementation's Customizer branch
 * produced an empty array on every request. That whole path is not reproduced.
 *
 * This is a port of, in the previous theme:
 *   vamtam/classes/elementor-bridge.php:921-1230  get_translated_kit()
 *   vamtam/classes/less-bridge.php:32-197         prepare_vars_for_export()
 *   vamtam/assets/css/src/fallback/additional-css-variables.php
 *   vamtam/classes/enqueues.php:558-601           print_theme_options()
 *   vamtam/classes/color.php                      contrast + rgb helpers
 *
 * Deliberately NOT ported from prepare_vars_for_export(): the background and
 * gradient composition machinery (lines 118-186 there). It is driven entirely
 * by `*-background-type` keys, and the kit translation produces none - it only
 * ever emits colours, typography, dimensions and glyph codes. Reproducing dead
 * branches would make this file three times longer and no more correct.
 *
 * VERIFIED: the output of this file is byte-identical to the
 * `<style id="vamtam-theme-options">` block captured in
 * `_project/snapshots/ref-a/html/`, all 171 properties, modulo the
 * `--vamtam-loading-animation` URL, which necessarily points into this theme.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print the token block.
 *
 * Hooked at `wp_print_styles` priority 1 so it precedes every stylesheet,
 * including Elementor's generated per-post CSS.
 *
 * @return void
 */
function piecyfer_print_tokens() {
	$tokens = piecyfer_get_tokens();

	echo '<style id="piecyfer-tokens">';
	echo ':root {';

	foreach ( $tokens as $name => $value ) {
		echo '--vamtam-' . esc_html( $name ) . ':' . wp_kses_data( $value ) . ";\n";
	}

	echo "--vamtam-loading-animation:url('" . esc_attr( PIECYFER_THEME_URI . 'assets/images/loader-ring.gif' ) . "');\n";

	echo '}';
	echo '</style>';
}
add_action( 'wp_print_styles', 'piecyfer_print_tokens', 1 );

/**
 * Build the full token map, in emission order.
 *
 * The static values come first and the kit-derived values after. That ordering
 * is not cosmetic - it is the order the captured block is in, and reordering it
 * would be a diff on every page for no reason.
 *
 * @return array<string,string> Property name (without the `--vamtam-` prefix) => value.
 */
function piecyfer_get_tokens() {
	return array_merge(
		piecyfer_static_tokens(),
		piecyfer_prepare_tokens( piecyfer_kit_tokens() )
	);
}

/**
 * The 14 values that are not derived from anything.
 *
 * @return array<string,string>
 */
function piecyfer_static_tokens() {
	/*
	 * `carousel_background_color` is a leftover option key from the previous
	 * theme's Customizer. It is unset on this site, so the overlay colour falls
	 * back to black and its auto-contrast to white - which is what the captures
	 * show. The lookup is kept so the value stays configurable rather than
	 * becoming a magic constant.
	 */
	$overlay_color = get_option( 'carousel_background_color' );

	if ( empty( $overlay_color ) || ! is_string( $overlay_color ) ) {
		$overlay_color = '#000000';
	}

	// The accent whose rgb triple the default line colour is built from.
	$border_accent = 7;

	return array(
		/*
		 * Nested `var()` is intentional: the line colour has to follow the
		 * accent, and there is no other way to express that in a custom
		 * property.
		 */
		'default-bg-color'         => '#fff',
		'default-line-color'       => 'rgba( var( --vamtam-accent-color-' . $border_accent . '-rgb ), 1 )',

		'small-padding'            => '20px',

		'horizontal-padding'       => '50px',
		'vertical-padding'         => '30px',

		'horizontal-padding-large' => '60px',
		'vertical-padding-large'   => '60px',

		'no-border-link'           => 'none',

		'border-radius'            => '0px',
		'border-radius-oval'       => '0px',
		'border-radius-small'      => '0px',

		'overlay-color'            => $overlay_color,
		/*
		 * Lower-case on purpose. The previous theme computed this one inline
		 * with `'#ffffff'` while the accent contrasts went through
		 * VamtamColor::get_contrast_color(), which returns `'#FFFFFF'`. Both
		 * casings are in the captured token block; normalising them would be a
		 * byte diff for nothing.
		 */
		'overlay-color-hc'         => piecyfer_luminance( $overlay_color ) > 0.4 ? '#000000' : '#ffffff',

		'box-outer-padding'        => '60px',
	);
}

/**
 * Translate the active Elementor kit into the theme's option shape.
 *
 * Returns a partly nested array; `piecyfer_prepare_tokens()` flattens and
 * sanitises it. Splitting the two keeps this function a readable statement of
 * *what maps to what*.
 *
 * @return array<string,mixed>
 */
function piecyfer_kit_tokens() {
	$kit = piecyfer_get_kit_settings();

	if ( empty( $kit ) ) {
		return array();
	}

	$opts = array();

	// Links. The kit wins; body_color is the fallback.
	if ( isset( $kit['link_normal_color'] ) ) {
		$opts['body-link-regular'] = $kit['link_normal_color'];
		$opts['body-link-visited'] = $kit['link_normal_color'];
	} elseif ( isset( $kit['body_color'] ) ) {
		$opts['body-link-regular'] = $kit['body_color'];
		$opts['body-link-visited'] = $kit['body_color'];
	}

	if ( isset( $kit['link_hover_color'] ) ) {
		$opts['body-link-hover']  = $kit['link_hover_color'];
		$opts['body-link-active'] = $kit['link_hover_color'];
	}

	if ( isset( $kit['body_background_color'] ) ) {
		$opts['body-background-color'] = $kit['body_background_color'];
	}

	// Input border radius: all four sides must be set, or the shorthand is wrong.
	if ( isset( $kit['form_field_border_radius'] ) ) {
		$radius = $kit['form_field_border_radius'];
		$unit   = isset( $radius['unit'] ) ? $radius['unit'] : '';

		if ( isset( $radius['top'], $radius['right'], $radius['bottom'], $radius['left'] )
			&& '' !== $radius['top'] && '' !== $radius['right']
			&& '' !== $radius['bottom'] && '' !== $radius['left'] ) {
			$opts['input-border-radius'] = $radius['top'] . $unit . ' ' . $radius['right'] . $unit . ' ' .
				$radius['bottom'] . $unit . ' ' . $radius['left'] . $unit;
		}
	}

	if ( isset( $kit['__globals__']['form_field_border_color'] ) ) {
		$border_color = piecyfer_resolve_global( $kit, $kit['__globals__']['form_field_border_color'] );

		$opts['input-border-color'] = ( null === $border_color ) ? 'transparent' : $border_color;
	}

	// Buttons: button_hover_background_color -> btn-hover-bg-color, and so on.
	$button_opts = array(
		'button_text_color'             => 'btn-text-color',
		'button_hover_text_color'       => 'btn-hover-text-color',
		'button_background_color'       => 'btn-bg-color',
		'button_hover_background_color' => 'btn-hover-bg-color',
	);

	foreach ( $button_opts as $kit_key => $our_key ) {
		if ( isset( $kit[ $kit_key ] ) ) {
			$opts[ $our_key ] = $kit[ $kit_key ];
		}
	}

	if ( isset( $kit['container_width']['size'] ) ) {
		$opts['site-max-width'] = $kit['container_width']['size'];
	}

	// Typography colours. `body_color` becomes `primary-font-color`.
	$color_opts = array(
		'body_color' => 'primary-font-color',
		'h1_color'   => 'h1-color',
		'h2_color'   => 'h2-color',
		'h3_color'   => 'h3-color',
		'h4_color'   => 'h4-color',
		'h5_color'   => 'h5-color',
		'h6_color'   => 'h6-color',
	);

	foreach ( $color_opts as $kit_key => $our_key ) {
		if ( isset( $kit[ $kit_key ] ) ) {
			$opts[ $our_key ] = $kit[ $kit_key ];
			continue;
		}

		if ( isset( $kit['__globals__'][ $kit_key ] ) ) {
			$value = piecyfer_resolve_global( $kit, $kit['__globals__'][ $kit_key ] );

			if ( null !== $value ) {
				$opts[ $our_key ] = $value;
			}
		}
	}

	// Accents and the sticky header colour, in kit order.
	if ( ! empty( $kit['system_colors'] ) && is_array( $kit['system_colors'] ) ) {
		foreach ( $kit['system_colors'] as $system_color ) {
			if ( ! isset( $system_color['color'], $system_color['_id'] ) ) {
				continue;
			}

			$color_id = $system_color['_id'];

			if ( 'vamtam_sticky_header_bg_color' === $color_id ) {
				$opts['sticky-header-bg-color'] = $system_color['color'];
				continue;
			}

			if ( false === strpos( $color_id, 'vamtam_accent' ) || '' === $system_color['color'] ) {
				continue;
			}

			$index = substr( $color_id, -1 );

			$opts[ 'accent-color-' . $index ]          = $system_color['color'];
			$opts[ 'accent-color-' . $index . '-hc' ]  = piecyfer_contrast_color( $system_color['color'] );
			$opts[ 'accent-color-' . $index . '-rgb' ] = implode( ',', piecyfer_hex_to_rgb( $system_color['color'] ) );
		}
	}

	// Typography sets.
	$font_prefixes = array(
		'primary-font' => 'vamtam_primary_font',
		'h1'           => 'vamtam_h1',
		'h2'           => 'vamtam_h2',
		'h3'           => 'vamtam_h3',
		'h4'           => 'vamtam_h4',
		'h5'           => 'vamtam_h5',
		'h6'           => 'vamtam_h6',
	);

	if ( ! empty( $kit['system_typography'] ) && is_array( $kit['system_typography'] ) ) {
		foreach ( $font_prefixes as $our_prefix => $global_id ) {
			foreach ( $kit['system_typography'] as $typography ) {
				if ( ! isset( $typography['_id'] ) || $typography['_id'] !== $global_id ) {
					continue;
				}

				$opts[ $our_prefix ] = piecyfer_translate_typography( $typography );
			}
		}
	}

	// 28 glyph codes from the Elementor custom icon set.
	$opts = array_merge( $opts, piecyfer_icon_tokens() );

	return $opts;
}

/**
 * Translate one Elementor global typography set.
 *
 * The responsive fallback chain matters: an unset tablet size inherits the
 * desktop value and an unset phone size inherits the tablet one, which is how
 * `--vamtam-h1-font-size-tablet` exists at all. Desktop has no fallback, so an
 * unset desktop value stays empty and is dropped downstream - that is why
 * `--vamtam-h2-letter-spacing-desktop` is absent while its tablet and phone
 * siblings are `0px`.
 *
 * @param array $typography One entry of the kit's `system_typography`.
 * @return array<string,mixed>
 */
function piecyfer_translate_typography( array $typography ) {
	$set = array();

	if ( isset( $typography['typography_typography'] ) && 'custom' === $typography['typography_typography'] ) {
		$set['font-family'] = isset( $typography['typography_font_family'] ) ? $typography['typography_font_family'] : '';
	}

	$simple = array(
		'typography_font_weight'     => 'font-weight',
		'typography_font_style'      => 'font-style',
		'typography_text_transform'  => 'transform',
		'typography_text_decoration' => 'decoration',
		'color'                      => 'color',
	);

	foreach ( $simple as $elementor_key => $our_key ) {
		if ( isset( $typography[ $elementor_key ] ) ) {
			$set[ $our_key ] = $typography[ $elementor_key ];
		}
	}

	$responsive = array(
		'font_size'      => 'font-size',
		'line_height'    => 'line-height',
		'letter_spacing' => 'letter-spacing',
	);

	$steps = array(
		''        => 'desktop',
		'_tablet' => 'tablet',
		'_mobile' => 'phone',
	);

	foreach ( $responsive as $elementor_key => $our_key ) {
		$set[ $our_key ] = array(
			'desktop' => '',
			'tablet'  => '',
			'phone'   => '',
			'unit'    => array(
				'desktop' => '',
				'tablet'  => '',
				'phone'   => '',
			),
		);

		foreach ( $steps as $suffix => $step ) {
			$key = 'typography_' . $elementor_key . $suffix;

			if ( ! empty( $typography[ $key ]['size'] ) ) {
				$set[ $our_key ][ $step ]           = $typography[ $key ]['size'];
				$set[ $our_key ]['unit'][ $step ]   = isset( $typography[ $key ]['unit'] ) ? $typography[ $key ]['unit'] : '';
				continue;
			}

			$desktop_key = 'typography_' . $elementor_key;

			if ( 'tablet' === $step ) {
				$set[ $our_key ]['tablet']         = isset( $typography[ $desktop_key ]['size'] ) ? $typography[ $desktop_key ]['size'] : null;
				$set[ $our_key ]['unit']['tablet'] = isset( $typography[ $desktop_key ]['unit'] ) ? $typography[ $desktop_key ]['unit'] : null;
			} elseif ( 'phone' === $step ) {
				$set[ $our_key ]['phone']         = $set[ $our_key ]['tablet'];
				$set[ $our_key ]['unit']['phone'] = $set[ $our_key ]['unit']['tablet'];
			}
		}
	}

	return $set;
}

/**
 * Read the glyph codes of the `theme-icons` Elementor custom icon set.
 *
 * Deliberately does NOT gate on Elementor Pro's Icons_Manager class, which is
 * what the previous implementation did. That gate means these 28 properties
 * disappear the moment Pro is deactivated, taking every theme icon on the site
 * with them. The icon set is ordinary post data and is readable without Pro.
 *
 * @return array<string,string>
 */
function piecyfer_icon_tokens() {
	$sets = get_posts(
		array(
			'post_type'              => 'elementor_icons',
			'posts_per_page'         => -1,
			'post_status'            => 'any',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	$style_url = '';

	foreach ( $sets as $set ) {
		$config = json_decode( (string) get_post_meta( $set->ID, 'elementor_custom_icon_set_config', true ), true );

		if ( isset( $config['name'], $config['url'] ) && 'theme-icons' === $config['name'] ) {
			$style_url = $config['url'];
			break;
		}
	}

	if ( '' === $style_url ) {
		return array();
	}

	$uploads = wp_upload_dir();

	if ( ! empty( $uploads['error'] ) ) {
		return array();
	}

	$style_path     = str_replace( $uploads['baseurl'], $uploads['basedir'], $style_url );
	$selection_path = preg_replace( '/style([^.]*)\.css/', 'selection$1.json', $style_path );

	if ( ! $selection_path || ! is_readable( $selection_path ) ) {
		return array();
	}

	$selection = json_decode( (string) file_get_contents( $selection_path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	if ( empty( $selection->icons ) ) {
		return array();
	}

	$tokens = array();

	foreach ( $selection->icons as $icon ) {
		if ( ! isset( $icon->properties->name, $icon->properties->code ) ) {
			continue;
		}

		$tokens[ 'icon-' . $icon->properties->name ] = '\\' . dechex( $icon->properties->code );
	}

	return $tokens;
}

/**
 * Flatten, sanitise and unit-join the translated kit values.
 *
 * Three things happen here, in order:
 *
 *   1. nested arrays are flattened with `-` joining the levels, so
 *      `['h1']['font-size']['tablet']` becomes `h1-font-size-tablet`;
 *   2. every value is duck-typed and either accepted, quoted or rejected;
 *   3. responsive numbers are joined to the unit stored alongside them, and
 *      the unit entries themselves are dropped so they never reach the CSS.
 *
 * @param array $raw Nested option array.
 * @return array<string,string>
 */
function piecyfer_prepare_tokens( array $raw ) {
	$flat = piecyfer_flatten( $raw );

	$vars = array();

	foreach ( $flat as $name => $value ) {
		if ( null === $value ) {
			continue;
		}

		if ( ! preg_match( '/^[-\w\d]+$/i', $name ) ) {
			continue;
		}

		$prepared = piecyfer_prepare_value( $name, $value );

		if ( null !== $prepared ) {
			$vars[ $name ] = $prepared;
		}
	}

	$out = array();

	foreach ( $vars as $name => $value ) {
		if ( '' === $value || null === $value ) {
			continue;
		}

		if ( preg_match( '/-(desktop|tablet|phone)$/', $name ) ) {
			if ( ! is_numeric( $value ) ) {
				continue;
			}

			// A unitless value (line-height) has no matching `-unit-` sibling.
			$unit_name = preg_replace( '/-(desktop|tablet|phone)$/', '-unit-$1', $name );

			$out[ $name ] = isset( $vars[ $unit_name ] ) ? $value . $vars[ $unit_name ] : $value;

			continue;
		}

		// Bare `-font-size` / `-line-height` / `-letter-spacing` keys are the
		// containers of the responsive triples, never values in their own right.
		if ( preg_match( '/-(variant|font-size|line-height|letter-spacing)$/', $name ) ) {
			continue;
		}

		$out[ $name ] = $value;
	}

	// Drop the unit carriers; they exist only to be joined above.
	foreach ( array_keys( $out ) as $name ) {
		if ( preg_match( '/-unit-(desktop|tablet|phone)$/', $name ) ) {
			unset( $out[ $name ] );
		}
	}

	return $out;
}

/**
 * Recursively flatten a nested option array into `a-b-c` keys.
 *
 * @param array  $values Values to flatten.
 * @param string $prefix Accumulated key prefix.
 * @return array<string,mixed>
 */
function piecyfer_flatten( array $values, $prefix = '' ) {
	$flat = array();

	foreach ( $values as $key => $value ) {
		if ( is_array( $value ) ) {
			$flat = array_merge( $flat, piecyfer_flatten( $value, $prefix . $key . '-' ) );
			continue;
		}

		$flat[ $prefix . $key ] = $value;
	}

	return $flat;
}

/**
 * Duck-type one value into something safe to put after a colon in CSS.
 *
 * Returns null to reject the value entirely, which is how nonsense from a
 * half-filled kit control gets dropped instead of emitting `--vamtam-x:;`.
 *
 * @param string $name  Flattened property name.
 * @param mixed  $value Raw value.
 * @return string|int|float|null
 */
function piecyfer_prepare_value( $name, $value ) {
	if ( ! is_scalar( $value ) ) {
		return null;
	}

	$value = (string) $value;

	// Already carries a unit.
	if ( preg_match( '/(%|px|em|rem|vw|vh)$/i', $value ) ) {
		return $value;
	}

	// Icon glyph escape, e.g. `\e919` - must be quoted to survive as content.
	if ( preg_match( '/^\\\\[0-9a-f]+$/i', $value ) ) {
		return "'" . $value . "'";
	}

	if ( is_numeric( $value ) ) {
		// Unitless by nature.
		if ( false !== strpos( $name, 'line-height' ) ) {
			return $value;
		}

		// Bare dimensions are pixels.
		if ( preg_match( '/(size|width|height|padding|margin)$/', $name ) ) {
			return $value . 'px';
		}

		return $value;
	}

	// 3- and 6-digit hex, and empty values on a `*-color` property.
	if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ) {
		return $value;
	}

	// Font stacks arrive comma-separated and are already valid CSS.
	if ( preg_match( '/-font-family$/', $name ) && false !== strpos( $value, ',' ) ) {
		return $value;
	}

	// Keyword-valued properties.
	if ( preg_match( '/-(transform|decoration|font-weight)$/', $name ) ) {
		return $value;
	}

	// URLs, and single font-family names, need quoting.
	if ( preg_match( '/^(http|url)/i', $value )
		|| ( preg_match( '/(family|weight)$/', $name ) && isset( $value[0] ) && ! in_array( $value[0], array( '"', "'" ), true ) ) ) {
		return "'" . str_replace( "'", '"', $value ) . "'";
	}

	/*
	 * Anything left over is accepted only if it is a colour-ish or link-ish
	 * property - which covers 8-digit hex with alpha, `rgba(...)` and the
	 * comma-separated rgb triples - or if every word in it is a CSS keyword.
	 */
	if ( preg_match( '/\bfamily\b|\burl\b|\bcolor\b|\bbody-link\b/i', $name ) ) {
		return $value;
	}

	$keywords = array(
		'top', 'right', 'bottom', 'left', 'fixed', 'static', 'scroll', 'cover', 'contain',
		'auto', 'repeat', 'repeat-x', 'repeat-y', 'no-repeat', 'center', 'normal', 'italic',
		'bold', '100', '200', '300', '400', '500', '600', '700', '800', '900', 'transparent',
	);

	foreach ( explode( ' ', $value ) as $word ) {
		if ( ! in_array( $word, $keywords, true ) ) {
			return null;
		}
	}

	return $value;
}

/**
 * Read the active Elementor kit's settings.
 *
 * Prefers Elementor's own kits manager, which is what the previous
 * implementation used and therefore what the captured output was produced by.
 * Falls back to the raw postmeta so the tokens still resolve if this runs
 * before Elementor has booted.
 *
 * @return array
 */
function piecyfer_get_kit_settings() {
	static $settings = null;

	if ( null !== $settings ) {
		return $settings;
	}

	$settings = array();

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::instance()->kits_manager ) ) {
		$kit = \Elementor\Plugin::instance()->kits_manager->get_active_kit();

		if ( $kit ) {
			$data = $kit->get_data();

			if ( ! empty( $data['settings'] ) && is_array( $data['settings'] ) ) {
				$settings = $data['settings'];

				return $settings;
			}
		}
	}

	$kit_id = (int) get_option( 'elementor_active_kit' );

	if ( $kit_id ) {
		$meta = get_post_meta( $kit_id, '_elementor_page_settings', true );

		if ( is_array( $meta ) ) {
			$settings = $meta;
		}
	}

	return $settings;
}

/**
 * Resolve an Elementor global reference against the kit itself.
 *
 * A global reference looks like `globals/colors?id=vamtam_accent_7`. Elementor
 * resolves these through its data manager; this resolves them directly out of
 * the kit's own `system_colors` / `system_typography` arrays, which is the same
 * answer and does not require Elementor Pro or a booted data layer.
 *
 * @param array  $kit        Kit settings.
 * @param string $global_key Global reference.
 * @return string|null
 */
function piecyfer_resolve_global( array $kit, $global_key ) {
	if ( ! is_string( $global_key ) || '' === $global_key ) {
		return null;
	}

	$query = wp_parse_url( $global_key, PHP_URL_QUERY );

	if ( ! $query ) {
		return null;
	}

	parse_str( $query, $args );

	if ( empty( $args['id'] ) ) {
		return null;
	}

	$is_typography = ( false !== strpos( $global_key, 'globals/typography' ) );
	$collection    = $is_typography ? 'system_typography' : 'system_colors';

	if ( empty( $kit[ $collection ] ) || ! is_array( $kit[ $collection ] ) ) {
		return null;
	}

	foreach ( $kit[ $collection ] as $entry ) {
		if ( ! isset( $entry['_id'] ) || $entry['_id'] !== $args['id'] ) {
			continue;
		}

		if ( $is_typography ) {
			return isset( $entry['color'] ) && '' !== $entry['color'] ? $entry['color'] : null;
		}

		return isset( $entry['color'] ) && '' !== $entry['color'] ? $entry['color'] : null;
	}

	return null;
}

/**
 * Split a hex colour into its rgb components.
 *
 * Accepts 3-, 6- and 8-digit hex. The alpha channel of an 8-digit value is
 * ignored, which matters: `--vamtam-accent-color-7-rgb` is `0,0,0` even though
 * accent 7 is `#00000026`, because the variable exists to be fed into
 * `rgba( var(...), 1 )` where the alpha is supplied separately.
 *
 * @param string $hex Hex colour, with or without a leading `#`.
 * @return int[] Three components, R G B.
 */
function piecyfer_hex_to_rgb( $hex ) {
	$hex = ltrim( (string) $hex, '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	} elseif ( 8 === strlen( $hex ) ) {
		$hex = substr( $hex, 0, 6 );
	}

	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return array( 0, 0, 0 );
	}

	return array(
		hexdec( substr( $hex, 0, 2 ) ),
		hexdec( substr( $hex, 2, 2 ) ),
		hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * Pick black or white against a background, by WCAG 2.0 relative luminance.
 *
 * The 0.4 threshold and the exact casing of the two returned values are carried
 * over unchanged: `#000000` and `#FFFFFF` both appear in the captured token
 * block and a case change is a byte diff.
 *
 * @param string $hex Background colour.
 * @return string `#000000` or `#FFFFFF`.
 */
function piecyfer_contrast_color( $hex ) {
	return piecyfer_luminance( $hex ) > 0.4 ? '#000000' : '#FFFFFF';
}

/**
 * WCAG 2.0 relative luminance of a hex colour.
 *
 * @param string $hex Hex colour.
 * @return float
 */
function piecyfer_luminance( $hex ) {
	list( $red, $green, $blue ) = piecyfer_hex_to_rgb( $hex );

	$channels = array();

	foreach ( array( $red, $green, $blue ) as $index => $channel ) {
		$channel = $channel / 255;

		$channels[ $index ] = $channel < 0.03928
			? $channel / 12.92
			: pow( ( $channel + 0.055 ) / 1.055, 2.4 );
	}

	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}
