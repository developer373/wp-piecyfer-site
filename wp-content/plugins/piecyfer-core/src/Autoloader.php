<?php
/**
 * PSR-4 style autoloader for the PieCyfer\Core namespace.
 *
 * Hand-rolled rather than Composer-generated so the plugin can be dropped onto
 * a server and simply work — no `composer install` step, no vendor/ directory
 * to keep in sync, nothing to forget during a deploy.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	private const PREFIX = __NAMESPACE__ . '\\';

	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	public static function load( string $class ): void {
		if ( ! str_starts_with( $class, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$path     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';

		// realpath() + prefix check keeps a malformed class name from escaping
		// src/ via ../ segments.
		$real = realpath( $path );
		if ( false === $real || ! str_starts_with( $real, realpath( __DIR__ ) ) ) {
			return;
		}

		require_once $real;
	}
}
