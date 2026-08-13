<?php
/**
 * Backfill cache group allowlist.
 *
 * Defines which cache groups the backfill AJAX handlers accept when the Vue
 * reports read from or write to the internal cache.
 *
 * @since 9.11.0
 * @package MonsterInsights
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'monsterinsights_backfill_cache_allowed_groups' ) ) {
	/**
	 * Get the cache groups the backfill AJAX handlers accept.
	 *
	 * @since 9.11.0
	 *
	 * @return array List of allowed cache group slugs.
	 */
	function monsterinsights_backfill_cache_allowed_groups() {
		$groups = array(
			'overview',
			'custom_dashboard',
			'custom_dimensions',
			'traffic',
			'ecommerce',
			'publishers',
			'dimensions',
			'forms',
			'media',
		);

		/**
		 * Filter the cache groups the Vue reports may read from and write to.
		 *
		 * Lets Pro and addon reports register their own cache group without
		 * editing core.
		 *
		 * @since 9.11.0
		 *
		 * @param array $groups List of allowed cache group slugs.
		 */
		return apply_filters( 'monsterinsights_backfill_cache_allowed_groups', $groups );
	}
}
