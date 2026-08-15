<?php
/**
 * Plugin Name:       PieCyfer Core
 * Plugin URI:        https://piecyfer.com/
 * Description:       Owned replacements for the site's paid Elementor add-ons — widgets, theme-builder locations, popups and control extensions. No licence server, no expiry, no phone-home.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            PieCyfer
 * Author URI:        https://piecyfer.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       piecyfer-core
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.0';

/**
 * Elementor versions this plugin has been tested against.
 *
 * Below MIN we refuse to load, because the widget APIs we rely on are not
 * present. Above TESTED we still load — refusing would take the site down for
 * a routine Elementor update, which is exactly the fragility this plugin
 * exists to remove — but we surface an admin notice so the mismatch is not
 * discovered by a visitor.
 */
const ELEMENTOR_MIN    = '3.20.0';
const ELEMENTOR_TESTED = '4.2.2';
const PHP_MIN          = '8.0';

define( 'PIECYFER_CORE_FILE', __FILE__ );
define( 'PIECYFER_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PIECYFER_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/src/Autoloader.php';
Autoloader::register();

/**
 * Everything is deferred to `plugins_loaded` so that Elementor — whatever load
 * order the site ends up with — is present before we look for it.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		$blocker = requirements_blocker();

		if ( null !== $blocker ) {
			add_action(
				'admin_notices',
				static function () use ( $blocker ): void {
					printf(
						'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
						esc_html__( 'PieCyfer Core is inactive:', 'piecyfer-core' ),
						esc_html( $blocker )
					);
				}
			);
			return;
		}

		Plugin::instance();
	},
	// After Elementor (10) so its classes exist, before themes hook in at 20.
	15
);

/**
 * Return a human-readable reason the plugin cannot run, or null if it can.
 *
 * Kept as a plain function rather than a class method so it works even when the
 * autoloader itself is the thing that is broken.
 */
function requirements_blocker(): ?string {
	if ( version_compare( PHP_VERSION, PHP_MIN, '<' ) ) {
		return sprintf(
			/* translators: 1: required PHP version, 2: current PHP version */
			__( 'PHP %1$s or newer is required. This server runs PHP %2$s.', 'piecyfer-core' ),
			PHP_MIN,
			PHP_VERSION
		);
	}

	if ( ! did_action( 'elementor/loaded' ) ) {
		return __( 'Elementor must be installed and activated.', 'piecyfer-core' );
	}

	if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, ELEMENTOR_MIN, '<' ) ) {
		return sprintf(
			/* translators: 1: required Elementor version, 2: installed Elementor version */
			__( 'Elementor %1$s or newer is required. Version %2$s is installed.', 'piecyfer-core' ),
			ELEMENTOR_MIN,
			ELEMENTOR_VERSION
		);
	}

	return null;
}
