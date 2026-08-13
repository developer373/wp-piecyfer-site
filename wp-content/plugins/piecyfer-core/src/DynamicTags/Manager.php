<?php
/**
 * Registers PieCyfer's replacement dynamic tags and their groups.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\DynamicTags;

use Elementor\Core\DynamicTags\Manager as TagsManager;

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic tags had to move ahead of the widgets that use them.
 *
 * The theme title/logo widgets do not store their content as text — they store
 * a tag reference, with Elementor's "Add Your Heading Text Here" placeholder
 * sitting in the literal field. Register the widget without the tag and it
 * renders the placeholder.
 *
 * Four of these tags are also attached to plain *free* Elementor widgets
 * (`heading.title`, `column.background_image`), so they are not only a Pro
 * concern: without them, widgets we were never replacing would degrade too.
 */
final class Manager {

	/**
	 * Groups shown in the dynamic-tag picker. Ids match Elementor Pro's so the
	 * picker looks unchanged to whoever edits these pages.
	 */
	private const GROUPS = array(
		'post'    => 'Post',
		'archive' => 'Archive',
		'site'    => 'Site',
		'media'   => 'Media',
		'action'  => 'Action',
	);

	/**
	 * Tag classes, keyed by the tag name stored in `__dynamic__`.
	 *
	 * `popup` is deliberately absent: it renders a link that drives Elementor
	 * Pro's popup system, so its 6 uses are blocked on the popup work rather
	 * than on tags. See _project/02-WIDGET-REBUILD-SPEC.md.
	 *
	 * @var array<class-string>
	 */
	private const TAGS = array(
		Tags\SiteTitle::class,
		Tags\SiteLogo::class,
		Tags\PostTitle::class,
		Tags\PostTerms::class,
		Tags\PostFeaturedImage::class,
		Tags\ArchiveTitle::class,
		Tags\CurrentDateTime::class,
		Tags\InternalUrl::class,
	);

	public static function init(): void {
		add_action( 'elementor/dynamic_tags/register', array( self::class, 'register' ) );
	}

	public static function register( TagsManager $tags_manager ): void {
		foreach ( self::GROUPS as $id => $title ) {
			$tags_manager->register_group( $id, array( 'title' => $title ) );
		}

		foreach ( self::TAGS as $class ) {
			try {
				if ( ! class_exists( $class ) ) {
					throw new \RuntimeException( "tag class not found: {$class}" );
				}
				$tags_manager->register( new $class() );
			} catch ( \Throwable $e ) {
				// One broken tag must not take the others down with it.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[piecyfer-core] dynamic tag ' . $class . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}
	}
}
