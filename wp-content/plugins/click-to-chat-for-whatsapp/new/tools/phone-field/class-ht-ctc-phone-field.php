<?php
/**
 * Phone field (intl-tel-input) — locator for the vendored library.
 *
 * Single locator for the vendored intl-tel-input library files and version details.
 * Provides a unified API for the admin UIs and front-end phone field components to
 * resolve library URLs, versions, and localized interface strings.
 *
 * Note: `intlTelInput.esm.js` is an ES module and should be dynamically imported
 * (import()) rather than registered as a classic script.
 *
 * This class registers and enqueues nothing directly; consumers retrieve URLs via
 * assets() and handle enqueuing and script loading according to their needs.
 *
 * @package Click_To_Chat
 * @since 4.42
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HT_CTC_Phone_Field' ) ) {

	/**
	 * Locates the vendored intl-tel-input files and reports its version.
	 */
	class HT_CTC_Phone_Field {

		/**
		 * Vendored library name.
		 *
		 * @var string
		 */
		const LIBRARY = 'intl-tel-input';

		/**
		 * Version of the vendored library.
		 *
		 * Matches the copy in intl-tel-input/. The library embeds its own version,
		 * so it can always be confirmed with:
		 * `grep 'version:' intl-tel-input/js/intlTelInput.esm.js`
		 *
		 * Set by the vendoring tooling in the plugin's development repository,
		 * which reads it from that same source — never typed by hand.
		 *
		 * @var string
		 */
		const VERSION = '29.1.2';

		/**
		 * Contract version for consumers of assets().
		 *
		 * Bump ONLY on a breaking change to the returned array (a key removed
		 * or its meaning changed). Adding a key is not breaking.
		 *
		 * @var int
		 */
		const API = 1;



		/**
		 * Describe the vendored library: version plus URLs for every file.
		 *
		 * Minified variants are used unless HT_CTC_DEBUG_MODE is defined, so a
		 * consumer never has to repeat that decision.
		 *
		 * @since 4.42
		 *
		 * @return array {
		 *     @type string $library  Library name — 'intl-tel-input'.
		 *     @type string $version  Vendored library version, e.g. '29.1.2'.
		 *     @type int    $api      Contract version of this array shape.
		 *     @type bool   $min      Whether the URLs point at minified files.
		 *     @type string $js       ES-module entry. import() it; do NOT enqueue.
		 *     @type string $utils    Lazy utils module (library `loadUtils` option).
		 *     @type string $css      Stylesheet URL.
		 *     @type string $img      Flag-sprite directory URL (referenced by the CSS).
		 *     @type string $dir_url  Base URL of the vendored directory.
		 * }
		 *
		 * Every URL is a BARE path — no `?ver=`. The consumer that puts a file on
		 * the page adds the cache-buster: enqueue it and WP stamps it; import() it
		 * or build a raw <link> and you must append `?ver=` . HT_CTC_VERSION
		 * yourself (these files change on plugin releases — a bare URL leaves the
		 * browser serving the previous release's copy).
		 */
		public static function assets() {

			$min     = defined( 'HT_CTC_DEBUG_MODE' ) ? '' : '.min';
			$dir_url = plugins_url( 'new/tools/phone-field/intl-tel-input/', HT_CTC_PLUGIN_FILE );

			$assets = array(
				'library' => self::LIBRARY,
				'version' => self::VERSION,
				'api'     => self::API,
				'min'     => ( '' !== $min ),

				'js'      => $dir_url . "js/intlTelInput.esm{$min}.js",

				// utils.js is shipped pre-compiled by upstream; no .min variant needed.
				'utils'   => $dir_url . 'js/utils.js',
				'css'     => $dir_url . "css/intlTelInput{$min}.css",
				'img'     => $dir_url . 'img/',

				'dir_url' => $dir_url,
			);

			/**
			 * Filter the vendored phone-field asset URLs.
			 *
			 * Lets a site point the library elsewhere (a CDN, a shared copy)
			 * without touching plugin files. Keys are the contract — a filter
			 * that drops one breaks its consumers.
			 *
			 * @since 4.42
			 *
			 * @param array $assets Asset descriptor, see the return doc above.
			 */
			return apply_filters( 'ht_ctc_fh_phone_field_assets', $assets );
		}


		/**
		 * Retrieve localized interface strings for the library's `uiTranslations`.
		 *
		 * Reads pre-compiled translation maps from `locale-strings.php` based on language tag.
		 * Returns an array of translated strings or an empty array for English/unknown locales.
		 *
		 * @since 4.42
		 *
		 * @param string $language Language code: 'mr', 'pt_BR', 'zh-HK', 'en_US'.
		 * @return array Strings for the library's `uiTranslations`, or empty.
		 */
		public static function locale_strings( $language ) {

			static $all = null;

			$language = strtolower( str_replace( '_', '-', (string) $language ) );

			if ( ! preg_match( '/^[a-z]{2,3}(-[a-z0-9]{2,4})?$/', $language ) ) {
				return array();
			}

			// English needs nothing — the library's built-in strings are English.
			if ( 'en' === $language || 0 === strpos( $language, 'en-' ) ) {
				return array();
			}

			if ( null === $all ) {
				$file = HT_CTC_PLUGIN_DIR . 'new/tools/phone-field/locale-strings.php';
				$all  = file_exists( $file ) ? include $file : array();

				if ( ! is_array( $all ) ) {
					$all = array();
				}
			}

			// Full tag first ('zh-hk'), then the base ('pt-br' -> 'pt').
			$base = strtok( $language, '-' );

			$raw = null;
			foreach ( array( $language, $base ) as $candidate ) {
				if ( isset( $all[ $candidate ] ) ) {
					$raw = $all[ $candidate ];
					break;
				}
			}

			if ( empty( $raw ) || ! is_array( $raw ) ) {
				return array();
			}

			// If already associative (legacy/un-indexed structure), return directly.
			if ( isset( $raw['selectedCountryAriaLabel'] ) ) {
				return $raw;
			}

			$keys = array(
				'selectedCountryAriaLabel',
				'noCountrySelected',
				'countryListAriaLabel',
				'searchPlaceholder',
				'clearSearchAriaLabel',
				'searchEmptyState',
			);

			$result = array();
			foreach ( $keys as $i => $key ) {
				if ( isset( $raw[ $i ] ) ) {
					$result[ $key ] = $raw[ $i ];
				}
			}

			if ( isset( $raw[6] ) && is_array( $raw[6] ) ) {
				$aria = $raw[6];
				// Re-populate exact[0] from searchEmptyState if exact[0] was omitted.
				if ( ! isset( $aria['exact'][0] ) && isset( $result['searchEmptyState'] ) ) {
					if ( ! isset( $aria['exact'] ) ) {
						$aria['exact'] = array();
					}
					$aria['exact'][0] = $result['searchEmptyState'];
					ksort( $aria['exact'] );
				}
				$result['searchSummaryAria'] = $aria;
			}

			return $result;
		}
	}
}

if ( ! defined( 'HT_CTC_PHONE_FIELD_VERSION' ) ) {
	// Version of the vendored library itself — for "is it new enough?" checks.
	define( 'HT_CTC_PHONE_FIELD_VERSION', HT_CTC_Phone_Field::VERSION );
}

if ( ! defined( 'HT_CTC_PHONE_FIELD_API' ) ) {
	// Presence of this constant IS the feature gate for PRO. See the class doc.
	define( 'HT_CTC_PHONE_FIELD_API', HT_CTC_Phone_Field::API );
}
