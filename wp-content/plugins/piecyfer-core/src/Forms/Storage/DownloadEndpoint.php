<?php
/**
 * The only way to read a stored form upload.
 *
 * Two ways in, both of which Pro's public-URL-in-an-email arrangement had
 * neither of:
 *
 *   1. **A signed link.** This is what goes into the notification email in place
 *      of Pro's permanent public URL. It carries the file id, an expiry and an
 *      HMAC over both. The recipient does not need a WordPress account — which
 *      matters, because `info@piecyfer.com` and `hr@piecyfer.com` are mailboxes,
 *      not users — but the link stops working, and cannot be extended by editing
 *      it.
 *   2. **A signed-in user with the capability.** For anyone going back through
 *      old enquiries after the link has expired.
 *
 * The signing key is derived from the site's salts, so it rotates when the salts
 * rotate (`_project/scripts/rotate-salts.php`) and lives in wp-config.php rather
 * than in the database. Rotating salts invalidates every outstanding link; the
 * capability path still works.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Storage;

defined( 'ABSPATH' ) || exit;

final class DownloadEndpoint {

	public const ACTION = 'piecyfer_form_file';

	/**
	 * How long a link mailed to a recipient stays good, in days.
	 */
	private const LINK_TTL_DAYS = 30;

	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'serve' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'serve' ) );
	}

	/**
	 * A time-limited, tamper-evident URL for one stored file.
	 */
	public static function signed_url( string $id, ?int $expires = null ): string {
		$expires ??= time() + ( self::ttl_days() * DAY_IN_SECONDS );

		return add_query_arg(
			array(
				'action' => self::ACTION,
				'f'      => $id,
				'e'      => $expires,
				's'      => self::signature( $id, $expires ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Stream a file, or fail with a status code and nothing else.
	 */
	public static function serve(): void {
		$id      = isset( $_GET['f'] ) ? sanitize_text_field( wp_unslash( $_GET['f'] ) ) : '';
		$expires = isset( $_GET['e'] ) ? absint( $_GET['e'] ) : 0;
		$sig     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		if ( ! self::may_read( $id, $expires, $sig ) ) {
			// One response for "no such file", "bad signature" and "expired", so
			// the endpoint cannot be used to probe which ids exist.
			wp_die(
				esc_html__( 'This download link is not valid or has expired.', 'piecyfer-core' ),
				esc_html__( 'Link expired', 'piecyfer-core' ),
				array( 'response' => 403 )
			);
		}

		$path = UploadStore::path( $id );
		$meta = UploadStore::meta( $id );

		if ( ! $path || ! $meta ) {
			wp_die(
				esc_html__( 'This download link is not valid or has expired.', 'piecyfer-core' ),
				esc_html__( 'Link expired', 'piecyfer-core' ),
				array( 'response' => 403 )
			);
		}

		nocache_headers();

		/*
		 * Always an attachment, always octet-stream, always nosniff. The stored
		 * MIME was verified at upload time, but there is no reason to ask a
		 * browser to render a stranger's file inline, and every reason not to.
		 */
		header( 'Content-Type: application/octet-stream' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header(
			sprintf(
				'Content-Disposition: attachment; filename="%s"',
				str_replace( array( '"', "\r", "\n" ), '', (string) ( $meta['name'] ?? 'download' ) )
			)
		);

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile

		exit;
	}

	/**
	 * Signature first, capability second.
	 */
	private static function may_read( string $id, int $expires, string $sig ): bool {
		if ( '' === $id ) {
			return false;
		}

		if ( '' !== $sig && $expires > 0 ) {
			if ( hash_equals( self::signature( $id, $expires ), $sig ) && $expires >= time() ) {
				return true;
			}
		}

		/**
		 * Who may fetch a stored upload without a signed link.
		 *
		 * @param string $capability
		 */
		$capability = (string) apply_filters( 'piecyfer/forms/download_capability', 'manage_options' );

		return current_user_can( $capability );
	}

	private static function signature( string $id, int $expires ): string {
		return hash_hmac( 'sha256', $id . '|' . $expires, self::key() );
	}

	/**
	 * Derived from the site salts: no new option row, no key in the database,
	 * and rotating salts revokes every link that is still in someone's inbox.
	 */
	private static function key(): string {
		return hash_hmac( 'sha256', 'piecyfer-forms-download-v1', wp_salt( 'secure_auth' ) );
	}

	private static function ttl_days(): int {
		/**
		 * Lifetime of a download link mailed to a form recipient, in days.
		 *
		 * @param int $days
		 */
		return max( 1, (int) apply_filters( 'piecyfer/forms/download_link_days', self::LINK_TTL_DAYS ) );
	}
}
