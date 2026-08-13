<?php

/**
 * Reports class.
 *
 * @since 6.0.0
 *
 * @package MonsterInsights
 * @subpackage Reports
 * @author  Chris Christoff
 */

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

function monsterinsights_reports_page_body_class($classes)
{
	if (! empty($_REQUEST['page']) && $_REQUEST['page'] === 'monsterinsights_reports') {
		$classes .= ' monsterinsights-reporting-page ';
	}

	return $classes;
}

add_filter('admin_body_class', 'monsterinsights_reports_page_body_class');

/**
 * Callback for getting all of the reports tabs for MonsterInsights.
 *
 * @return array Array of tab information.
 * @since 6.0.0
 * @access public
 *
 */
function monsterinsights_get_reports()
{
	/**
	 * Developer Alert:
	 *
	 * Per the README, this is considered an internal hook and should
	 * not be used by other developers. This hook's behavior may be modified
	 * or the hook may be removed at any time, without warning.
	 */
	$reports = apply_filters('monsterinsights_get_reports', array());

	return $reports;
}

/**
 * Callback to output the MonsterInsights reports page.
 *
 * @return void
 * @since 6.0.0
 * @access public
 *
 */
function monsterinsights_reports_page()
{
	// Redirect routes that have been migrated to the Vue 3 overview report page.
	// PHP cannot read the hash fragment, so we use JavaScript.
	// Non-migrated routes are preserved on this page (hash routes not listed in vue3Routes).
	// TODO: Remove this once the reports pages are fully migrated to the new app.
	$overview_url = admin_url( 'admin.php?page=monsterinsights_overview_report' );
	?>
	<script>
	(function () {
		var hash = window.location.hash;
		var path = hash.replace(/^#\/?/, '/');

		// Routes already migrated to the Vue 3 overview report app.
		var vue3Routes = [
			'/',
			// Traffic reports (Phase 1)
			'/traffic-overview',
			'/traffic-technology',
			'/traffic-landing-pages',
			'/traffic-campaign',
			'/traffic-source-medium',
			'/traffic-social',
			'/traffic-ai',
			'/countries',
			// eCommerce reports (Phase 2)
			'/ecommerce',
			'/ecommerce-coupons',
			'/cart-abandonment',
			'/ecommerce-funnel',
			'/ecommerce-purchases-by-location',
			'/ecommerce-spend-by-day',
			'/ecommerce-spend-by-hour',
			'/ecommerce-refunds',
			'/ecommerce-refunds-by-geo',
			'/ecommerce-product-feed',
			'/publishers',
			'/publishers-pages',
			'/custom-events',
			// Standalone reports (migrated to Vue 3)
			'/user-journey-report',
			'/forms',
			'/exceptions',
			'/media',
			'/dimensions',
			'/real-time',
			'/search-console',
			'/site-speed',
		];

		var shouldRedirect = hash === '' || hash === '#' || vue3Routes.indexOf(path) !== -1;

		if ( shouldRedirect ) {
			// Preserve the hash route when redirecting so the Vue 3 router picks it up.
			var targetUrl = <?php echo wp_json_encode( $overview_url ); ?>;
			if ( hash && hash !== '#' && hash !== '#/' ) {
				targetUrl += hash;
			}
			window.location.replace( targetUrl );
		}
	})();
	</script>
	<?php

	/**
	 * Developer Alert:
	 *
	 * Per the README, this is considered an internal hook and should
	 * not be used by other developers. This hook's behavior may be modified
	 * or the hook may be removed at any time, without warning.
	 */
	do_action('monsterinsights_head');
	echo monsterinsights_ublock_notice(); // phpcs:ignore
	monsterinsights_settings_error_page('monsterinsights-reports');
	monsterinsights_settings_inline_js();
}
