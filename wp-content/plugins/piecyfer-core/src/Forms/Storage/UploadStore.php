<?php
/**
 * Where form uploads live now.
 *
 * ---------------------------------------------------------------------------
 * WHAT WAS WRONG WITH THE OLD ARRANGEMENT
 * ---------------------------------------------------------------------------
 * Pro writes uploads to `wp-content/uploads/elementor/forms/` — inside the web
 * root, served by the web server, with no authentication (upload.php:378-397,
 * :496-531). Its only protections are an `.htaccess` whose `Content-Disposition`
 * half sits inside `<ifModule mod_headers.c>` (silently a no-op without that
 * module, and entirely absent on nginx) and an `index.php` that stops directory
 * listing. Filenames come from `uniqid()`, which is not random: it is the
 * current time in microseconds, hex-encoded. Anyone who knows roughly when a
 * submission happened can enumerate a very small keyspace.
 *
 * 32 real CVs are sitting in that directory right now. Every one of them fetches
 * with HTTP 200 from the open internet. That is a live confidentiality problem
 * for a site whose five job-application forms collect them.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS DOES INSTEAD
 * ---------------------------------------------------------------------------
 *  * Files go **outside the web root** (see {@see self::default_dir()}), so no
 *    web-server configuration mistake can expose them.
 *  * Names are 128 bits from `random_bytes()`, not a timestamp.
 *  * The original filename, MIME type, size and origin are kept in a sidecar
 *    JSON file, so a download can be served with the name the applicant used
 *    without that name ever being part of a URL.
 *  * Everything is reachable only through {@see DownloadEndpoint}, which wants
 *    either a signed link or a logged-in user with the right capability.
 *  * {@see self::gc()} deletes anything past the retention window.
 *
 * The directory still gets `index.php`, `.htaccess` and `web.config` deny files.
 * They are belt-and-braces for the case where the resolved directory turns out
 * to be web-served after all — {@see self::preflight()} will tell you whether it
 * is.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Storage;

defined( 'ABSPATH' ) || exit;

final class UploadStore {

	/**
	 * How long a stored upload is kept, in days.
	 *
	 * PROPOSAL, NOT A DECISION — the lead owns this number. 180 days is long
	 * enough that an HR process finishes and short enough that we are not sitting
	 * on a decade of strangers' CVs. Nothing sweeps until the module is enabled
	 * and its cron event is scheduled.
	 */
	private const RETENTION_DAYS = 180;

	/**
	 * The absolute path uploads are written to, created on demand.
	 */
	public static function dir(): string {
		$dir = self::configured_dir();

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		self::write_guard_files( $dir );

		return $dir;
	}

	/**
	 * The configured path, without touching the filesystem.
	 */
	public static function configured_dir(): string {
		if ( defined( 'PIECYFER_FORMS_UPLOAD_DIR' ) && PIECYFER_FORMS_UPLOAD_DIR ) {
			$dir = (string) PIECYFER_FORMS_UPLOAD_DIR;
		} else {
			$dir = self::default_dir();
		}

		/**
		 * Where form uploads are stored.
		 *
		 * Must be outside the document root. Set the PIECYFER_FORMS_UPLOAD_DIR
		 * constant in wp-config.php in preference to this filter — a constant is
		 * read before any plugin can be tricked into changing it.
		 *
		 * @param string $dir Absolute path, no trailing slash.
		 */
		$dir = (string) apply_filters( 'piecyfer/forms/upload_dir', $dir );

		return untrailingslashit( wp_normalize_path( $dir ) );
	}

	/**
	 * A default that is genuinely outside the web root on this install.
	 *
	 * `dirname( ABSPATH )` is not good enough here. WordPress lives at
	 * `C:/xampp/htdocs/piecyfer` and is served from `http://localhost/piecyfer`,
	 * so the document root is `C:/xampp/htdocs` — one level *above* the WordPress
	 * directory. Anything written to `dirname( ABSPATH )` would still be fetchable
	 * over HTTP.
	 *
	 * So: work out the document root (from the server when we have it, otherwise
	 * by stripping the site URL's path from ABSPATH), and go one level above
	 * that.
	 */
	public static function default_dir(): string {
		return dirname( self::document_root() ) . '/piecyfer-private/form-uploads';
	}

	/**
	 * Best available answer for the document root, normalised.
	 */
	public static function document_root(): string {
		$root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? wp_normalize_path( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) ) : '';

		if ( $root && is_dir( $root ) ) {
			return untrailingslashit( $root );
		}

		// CLI and cron: derive it from the install path minus the site URL path.
		$abspath   = untrailingslashit( wp_normalize_path( ABSPATH ) );
		$url_path  = (string) wp_parse_url( site_url(), PHP_URL_PATH );
		$url_path  = trim( $url_path, '/' );
		$segments  = '' === $url_path ? array() : explode( '/', $url_path );

		foreach ( $segments as $ignored ) {
			unset( $ignored );
			$abspath = dirname( $abspath );
		}

		return untrailingslashit( $abspath );
	}

	/**
	 * Store one uploaded file.
	 *
	 * @param array<string,mixed> $file    One entry from the fixed-up $_FILES tree.
	 * @param array<string,mixed> $context Origin info kept in the sidecar.
	 *
	 * @return array{id:string,path:string,name:string,size:int,mime:string}|null Null when the move failed.
	 */
	public static function store( array $file, array $context = array() ): ?array {
		$dir = self::dir();

		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return null;
		}

		$original  = (string) ( $file['name'] ?? '' );
		$extension = strtolower( (string) pathinfo( $original, PATHINFO_EXTENSION ) );

		/*
		 * 32 hex characters from the CSPRNG. Pro's uniqid() is the current time
		 * to the microsecond — sequential, and brute-forceable inside any window
		 * an attacker can guess.
		 */
		$id       = bin2hex( random_bytes( 16 ) );
		$filename = $extension ? $id . '.' . $extension : $id;
		$new_file = trailingslashit( $dir ) . $filename;

		if ( ! self::move( (string) ( $file['tmp_name'] ?? '' ), $new_file ) ) {
			return null;
		}

		// Owner-readable only where the platform honours it. On Windows this is
		// a no-op, which is one more reason the directory is outside the root.
		@chmod( $new_file, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$meta = array(
			'id'         => $id,
			'file'       => $filename,
			'name'       => sanitize_file_name( $original ),
			'size'       => (int) ( $file['size'] ?? 0 ),
			// The type the *contents* sniffed as, never the client's Content-Type.
			'mime'       => (string) ( $file['pcf_verified_mime'] ?? '' ) ?: 'application/octet-stream',
			'created'    => time(),
			'expires'    => time() + ( self::retention_days() * DAY_IN_SECONDS ),
			'form_id'    => (string) ( $context['form_id'] ?? '' ),
			'post_id'    => (int) ( $context['post_id'] ?? 0 ),
			'field_id'   => (string) ( $context['field_id'] ?? '' ),
			'ip_hash'    => hash( 'sha256', (string) ( $context['ip'] ?? '' ) . wp_salt( 'nonce' ) ),
		);

		self::write_meta( $id, $meta );

		return array(
			'id'   => $id,
			'path' => $new_file,
			'name' => $meta['name'],
			'size' => $meta['size'],
			'mime' => $meta['mime'],
		);
	}

	/**
	 * Move the temp file into place.
	 *
	 * Indirected through a filter purely so the test harness can exercise the
	 * whole upload path without a real HTTP multipart request — `is_uploaded_file`
	 * is false for anything the harness creates, and it must stay true for
	 * anything a visitor sends.
	 */
	private static function move( string $tmp_name, string $destination ): bool {
		/**
		 * Override the move-uploaded-file step.
		 *
		 * @param null|bool $handled Return a boolean to take over.
		 */
		$handled = apply_filters( 'piecyfer/forms/move_uploaded_file', null, $tmp_name, $destination );

		if ( null !== $handled ) {
			return (bool) $handled;
		}

		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return false;
		}

		return move_uploaded_file( $tmp_name, $destination );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function meta( string $id ): ?array {
		if ( ! self::is_valid_id( $id ) ) {
			return null;
		}

		$path = self::meta_path( $id );

		if ( ! is_readable( $path ) ) {
			return null;
		}

		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return is_array( $data ) ? $data : null;
	}

	/**
	 * The absolute path of a stored file, or null when it is gone or the id is
	 * not one of ours.
	 *
	 * The realpath prefix test is the traversal guard: an id that somehow
	 * resolved outside the store never gets served.
	 */
	public static function path( string $id ): ?string {
		$meta = self::meta( $id );

		if ( ! $meta || empty( $meta['file'] ) ) {
			return null;
		}

		$dir      = self::configured_dir();
		$path     = trailingslashit( $dir ) . (string) $meta['file'];
		$resolved = realpath( $path );
		$root     = realpath( $dir );

		if ( ! $resolved || ! $root || ! str_starts_with( wp_normalize_path( $resolved ), trailingslashit( wp_normalize_path( $root ) ) ) ) {
			return null;
		}

		return $resolved;
	}

	public static function delete( string $id ): void {
		$path = self::path( $id );

		if ( $path ) {
			wp_delete_file( $path );
		}

		$meta_path = self::meta_path( $id );

		if ( self::is_valid_id( $id ) && file_exists( $meta_path ) ) {
			wp_delete_file( $meta_path );
		}
	}

	/**
	 * Delete everything past its retention date.
	 *
	 * Runs from the daily cron event Module schedules — and only ever when the
	 * module is enabled, so nothing on disk moves while this code is dormant.
	 *
	 * @return int Files removed.
	 */
	public static function gc(): int {
		$dir = self::configured_dir();

		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$removed = 0;
		$now     = time();

		foreach ( (array) glob( trailingslashit( $dir ) . '*.json' ) as $meta_path ) {
			$meta = json_decode( (string) file_get_contents( (string) $meta_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if ( ! is_array( $meta ) || empty( $meta['id'] ) ) {
				continue;
			}

			$expires = (int) ( $meta['expires'] ?? 0 );

			if ( $expires > 0 && $expires > $now ) {
				continue;
			}

			self::delete( (string) $meta['id'] );
			++$removed;
		}

		return $removed;
	}

	public static function retention_days(): int {
		/**
		 * How long uploads are kept.
		 *
		 * @param int $days
		 */
		return max( 1, (int) apply_filters( 'piecyfer/forms/upload_retention_days', self::RETENTION_DAYS ) );
	}

	/**
	 * Diagnostics for the cut-over checklist. Read-only; touches nothing.
	 *
	 * @return array<string,mixed>
	 */
	public static function preflight(): array {
		$dir  = self::configured_dir();
		$root = self::document_root();

		return array(
			'dir'                => $dir,
			'document_root'      => $root,
			'exists'             => is_dir( $dir ),
			'writable'           => is_dir( $dir ) ? is_writable( $dir ) : is_writable( dirname( $dir ) ),
			'outside_web_root'   => ! str_starts_with( trailingslashit( $dir ), trailingslashit( $root ) ),
			'retention_days'     => self::retention_days(),
			'legacy_pro_dir'     => wp_normalize_path( (string) ( wp_upload_dir()['basedir'] ?? '' ) ) . '/elementor/forms',
		);
	}

	private static function is_valid_id( string $id ): bool {
		return 1 === preg_match( '/^[a-f0-9]{32}$/', $id );
	}

	private static function meta_path( string $id ): string {
		return trailingslashit( self::configured_dir() ) . $id . '.json';
	}

	/**
	 * @param array<string,mixed> $meta
	 */
	private static function write_meta( string $id, array $meta ): void {
		file_put_contents( self::meta_path( $id ), (string) wp_json_encode( $meta ), LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		@chmod( self::meta_path( $id ), 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Deny files, for the case where the directory is web-served after all.
	 *
	 * Written once — the `index.php` probe is the same early-out Pro uses
	 * (upload.php:435), and it is why Pro never noticed that its own guard files
	 * had gone missing at some point in this site's history.
	 */
	private static function write_guard_files( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array(
			'index.php'  => "<?php\n// Silence is golden.\n",
			'.htaccess'  => "Require all denied\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization>\n<deny users=\"*\" />\n</authorization></security></system.webServer></configuration>\n",
		);

		foreach ( $files as $name => $contents ) {
			$path = trailingslashit( $dir ) . $name;

			if ( file_exists( $path ) ) {
				continue;
			}

			file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		}
	}
}
