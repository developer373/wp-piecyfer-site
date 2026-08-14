<?php
/**
 * `upload` — 5 fields on this site, all of them collecting CVs.
 *
 * This is the field that needed rewriting rather than reproducing. See
 * 06-FORM-SPEC.md §6.7 and the header of {@see UploadStore} for what was wrong.
 * The short version of what changed:
 *
 *   1. Files are stored outside the web root under CSPRNG names and are served
 *      only through {@see DownloadEndpoint}. Pro wrote them into
 *      `wp-content/uploads/elementor/forms/` under `uniqid()` names with no
 *      authentication of any kind.
 *   2. File *contents* are checked, not just the extension.
 *   3. The per-file validation loop uses `continue`, not `return`
 *      (upload.php:344/351/358), so file 2 of 3 is actually inspected.
 *   4. `$_FILES['form_fields']` being absent no longer indexes into null
 *      (upload.php:326-341).
 *   5. There is a ceiling on the size and count a field with no explicit limits
 *      will accept.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Fields;

use PieCyfer\Core\Forms\AjaxHandler;
use PieCyfer\Core\Forms\FormRecord;
use PieCyfer\Core\Forms\Hooks;
use PieCyfer\Core\Forms\Security;
use PieCyfer\Core\Forms\Storage\DownloadEndpoint;
use PieCyfer\Core\Forms\Storage\UploadStore;

defined( 'ABSPATH' ) || exit;

final class UploadField extends FieldBase {

	public const MODE_LINK   = 'link';
	public const MODE_ATTACH = 'attach';
	public const MODE_BOTH   = 'both';

	/**
	 * Ceiling applied when the field itself sets no `file_sizes`, in MB.
	 *
	 * DIVERGENCE FROM PRO, and a deliberate one. Pro falls back to
	 * `wp_max_upload_size()`, which on this server is **3072 MB** because
	 * `post_max_size` is enormous. An endpoint that anyone on the internet can
	 * POST to should not accept a three-gigabyte file. All five live upload
	 * fields set `file_sizes = 5`, so this ceiling never applies to them.
	 */
	private const DEFAULT_MAX_MB = 25;

	/**
	 * Files accepted per field when `max_files` is empty.
	 */
	private const DEFAULT_MAX_FILES = 10;

	public function get_type(): string {
		return 'upload';
	}

	public function register(): void {
		parent::register();

		// Pro hooks the same method on its equivalent hook (upload.php:554).
		add_action( 'piecyfer/forms/process', array( $this, 'set_file_fields_values' ), 10, 2 );
	}

	// ------------------------------------------------------------ validation

	/**
	 * @param array<string,mixed> $field
	 */
	public function validation( $field, FormRecord $record, AjaxHandler $ajax_handler ): void {
		$this->fix_file_indices();

		$id    = (string) $field['id'];
		$files = $this->field_files( $id );

		if ( array() === $files ) {
			/*
			 * BUG FIX (spec §6.4.1). Pro does
			 * `count( $files[ $id ] )` and `foreach ( $files[ $id ] … )` with no
			 * guard, so a submission with no file part at all is a PHP warning
			 * plus a foreach over null. Here: nothing was uploaded, which is only
			 * an error if the field is required.
			 */
			if ( ! empty( $field['required'] ) ) {
				$ajax_handler->add_error( $id, $this->upload_error_message( UPLOAD_ERR_NO_FILE ) );
			}

			return;
		}

		$max_files = ! empty( $field['max_files'] ) ? (int) $field['max_files'] : $this->default_max_files();

		if ( count( $files ) > $max_files ) {
			$ajax_handler->add_error(
				$id,
				sprintf(
					/* translators: %d: The number of allowed files. */
					_n( 'You can upload only %d file.', 'You can upload up to %d files.', $max_files, 'piecyfer-core' ),
					$max_files
				)
			);

			return;
		}

		foreach ( $files as $file ) {
			$error = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );

			if ( UPLOAD_ERR_NO_FILE === $error ) {
				/*
				 * BUG FIX (spec §6.4.2). Pro `return`s here, so with two file
				 * inputs where the first is empty the second is never inspected
				 * at all — it goes straight to process_field() unvalidated.
				 */
				if ( ! empty( $field['required'] ) ) {
					$ajax_handler->add_error( $id, $this->upload_error_message( $error ) );

					return;
				}

				continue;
			}

			if ( UPLOAD_ERR_OK !== $error ) {
				$ajax_handler->add_error( $id, $this->upload_error_message( $error ) );

				continue;
			}

			if ( ! $this->is_extension_allowed( $field, $file ) ) {
				$ajax_handler->add_error( $id, esc_html__( 'This file type is not allowed.', 'piecyfer-core' ) );

				continue;
			}

			if ( ! $this->is_size_allowed( $field, $file ) ) {
				$ajax_handler->add_error( $id, esc_html__( 'This file exceeds the maximum allowed size.', 'piecyfer-core' ) );

				continue;
			}

			if ( ! $this->is_content_allowed( $file ) ) {
				/*
				 * Same visitor-facing message as a bad extension, on purpose: an
				 * uploader probing what gets through learns nothing from the
				 * difference. The detail goes to the log.
				 */
				$ajax_handler->add_error( $id, esc_html__( 'This file type is not allowed.', 'piecyfer-core' ) );

				continue;
			}
		}
	}

	/**
	 * Extension gate: allow-list first, then the deny-list, applied to **every**
	 * extension segment.
	 *
	 * DIVERGENCE FROM PRO on the last point. Pro takes
	 * `pathinfo( $name, PATHINFO_EXTENSION )`, which is the final segment only,
	 * so `cv.php.pdf` is judged as `pdf`. On an Apache with `mod_mime` and a
	 * misconfigured `AddHandler` that filename is executable. Our files are
	 * outside the web root and can never be executed by the server, so this is
	 * defence in depth rather than the load-bearing control — but it costs one
	 * loop.
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $file
	 */
	private function is_extension_allowed( array $field, array $file ): bool {
		$name = (string) ( $file['name'] ?? '' );

		if ( '' === $name ) {
			return false;
		}

		$allowed = $this->allowed_extensions( $field );
		$parts   = array_map( 'strtolower', array_slice( explode( '.', $name ), 1 ) );
		$last    = (string) end( $parts );

		if ( '' === $last || ! in_array( $last, $allowed, true ) ) {
			return false;
		}

		$blacklist = $this->blacklisted_extensions();

		foreach ( $parts as $part ) {
			if ( in_array( $part, $blacklist, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The editor's `file_types`, or Pro's default list when it is empty.
	 *
	 * Kept verbatim (upload.php:213-215) — the live fields set
	 * `pdf,doc,word,ppt`, and yes, `word` is not a file extension and `docx` is
	 * not in the list. That means a .docx CV is rejected today. It is a content
	 * decision, not a code one, so it is left exactly as the editor typed it.
	 *
	 * @param array<string,mixed> $field
	 *
	 * @return string[]
	 */
	private function allowed_extensions( array $field ): array {
		$types = (string) ( $field['file_types'] ?? '' );

		if ( '' === trim( $types ) ) {
			$types = 'jpg,jpeg,png,gif,pdf,doc,docx,ppt,pptx,odt,avi,ogg,m4a,mov,mp3,mp4,mpg,wav,wmv';
		}

		return array_filter( array_map( 'strtolower', array_map( 'trim', explode( ',', $types ) ) ) );
	}

	/**
	 * Pro's deny-list (upload.php:232-295) plus the extensions it forgot.
	 *
	 * @return string[]
	 */
	private function blacklisted_extensions(): array {
		$blacklist = array(
			'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phps',
			'phtm', 'phtml', 'pht', 'phar', 'phpt', 'hphp', 'shtml', 'shtm',
			'swf', 'html', 'htm', 'hta', 'xhtml', 'mhtml', 'mht',
			'asp', 'aspx', 'ascx', 'asmx', 'ashx', 'jsp', 'jspx', 'war', 'class',
			'cmd', 'csh', 'bat', 'com', 'exe', 'jar', 'js', 'mjs', 'lnk', 'msi',
			'htaccess', 'htpasswd', 'ini', 'ps1', 'ps2', 'psc1', 'py', 'pyc', 'rb',
			'pl', 'sh', 'bash', 'zsh', 'cgi', 'fcgi', 'tmp', 'inc', 'so', 'dll',
			'svg', 'svgz', 'xml', 'xsl', 'xslt', 'reg', 'scr', 'vbs', 'wsf',
		);

		/** This filter mirrors elementor_pro/forms/filetypes/blacklist — see Hooks. */
		return array_map( 'strtolower', (array) Hooks::apply_filters( 'filetypes/blacklist', $blacklist ) );
	}

	/**
	 * Size gate. Pro's strict `<` comparison (upload.php:194-201) is kept, with a
	 * ceiling substituted for its `wp_max_upload_size()` fallback.
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $file
	 */
	private function is_size_allowed( array $field, array $file ): bool {
		$allowed_mb = ! empty( $field['file_sizes'] ) ? (float) $field['file_sizes'] : (float) $this->default_max_mb();

		return (int) ( $file['size'] ?? 0 ) < $allowed_mb * ( 1024 ** 2 );
	}

	/**
	 * Content gate — the thing Pro does not do anywhere.
	 *
	 * Pro reads `$file['type']` (the browser-supplied Content-Type, trivially
	 * forged) into its arrays at upload.php:161 and then never looks at it. There
	 * is no `finfo`, no `wp_check_filetype_and_ext`, no `getimagesize`. A file
	 * called `cv.pdf` can contain anything at all.
	 *
	 * Here the file is sniffed, and:
	 *
	 *   * anything that sniffs as executable, script or markup is refused
	 *     outright, whatever it is called;
	 *   * otherwise the sniffed type must be one the claimed extension is allowed
	 *     to produce.
	 *
	 * When sniffing is impossible (no fileinfo extension, unreadable temp file)
	 * the file is allowed through and the fact is logged. Losing a real
	 * application because a PHP extension is missing on a server we control is
	 * the worse failure, and the extension allow-list plus out-of-root storage
	 * still apply.
	 *
	 * @param array<string,mixed> $file
	 */
	private function is_content_allowed( array $file ): bool {
		$tmp  = (string) ( $file['tmp_name'] ?? '' );
		$name = (string) ( $file['name'] ?? '' );

		if ( '' === $tmp || ! is_readable( $tmp ) ) {
			$this->log( "could not read temp file for {$name}; content check skipped" );

			return true;
		}

		$real_mime = self::sniff_mime( $tmp, $name );

		if ( '' === $real_mime ) {
			$this->log( "could not determine content type of {$name}; content check skipped" );

			return true;
		}

		if ( in_array( $real_mime, $this->never_allowed_mimes(), true ) ) {
			$this->log( "refused {$name}: contents sniff as {$real_mime}" );

			return false;
		}

		$extension = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		$expected  = $this->expected_mimes();

		if ( ! isset( $expected[ $extension ] ) ) {
			// An extension the editor allowed but we have no map for. The
			// never-allowed list above has already had its say.
			return true;
		}

		if ( ! in_array( $real_mime, $expected[ $extension ], true ) ) {
			$this->log( "refused {$name}: sniffed {$real_mime}, which is not a {$extension}" );

			return false;
		}

		return true;
	}

	/**
	 * The file's actual type, or '' when it cannot be determined.
	 *
	 * `finfo` first because it reads the bytes; `wp_check_filetype_and_ext()` as
	 * the fallback, which on a server without fileinfo falls back to the
	 * extension map and is therefore only as good as the filename.
	 */
	public static function sniff_mime( string $tmp, string $name ): string {
		if ( '' === $tmp || ! is_readable( $tmp ) ) {
			return '';
		}

		if ( class_exists( '\finfo' ) ) {
			$finfo = new \finfo( FILEINFO_MIME_TYPE );
			$mime  = (string) $finfo->file( $tmp );

			if ( '' !== $mime ) {
				return $mime;
			}
		}

		$checked = wp_check_filetype_and_ext( $tmp, $name );

		return (string) ( $checked['type'] ?? '' );
	}

	/**
	 * Sniffed types that are never acceptable, whatever the file is called.
	 *
	 * @return string[]
	 */
	private function never_allowed_mimes(): array {
		return (array) apply_filters(
			'piecyfer/forms/denied_mimes',
			array(
				'text/html',
				'application/xhtml+xml',
				'text/x-php',
				'application/x-php',
				'application/x-httpd-php',
				'application/x-httpd-php-source',
				'text/x-shellscript',
				'text/x-perl',
				'text/x-python',
				'text/x-ruby',
				'application/x-executable',
				'application/x-dosexec',
				'application/x-msdownload',
				'application/x-msdos-program',
				'application/x-mach-binary',
				'application/x-sharedlib',
				'application/vnd.microsoft.portable-executable',
				'application/x-shockwave-flash',
				'image/svg+xml',
				'application/hta',
			)
		);
	}

	/**
	 * Extension => the types its contents may legitimately sniff as.
	 *
	 * Deliberately generous for the legacy Office formats: `.doc`, `.ppt` and
	 * `.xls` are OLE compound documents and `libmagic` reports them variously as
	 * `application/msword`, `application/vnd.ms-office`, `application/CDFV2` or
	 * `application/x-ole-storage` depending on its version. Being strict here
	 * would reject real CVs, which is the failure mode that actually costs the
	 * business something. The OOXML formats are ZIP containers and often sniff as
	 * `application/zip` for the same reason.
	 *
	 * @return array<string,string[]>
	 */
	private function expected_mimes(): array {
		$ole   = array( 'application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/CDFV2-corrupt', 'application/x-ole-storage', 'application/octet-stream' );
		$ooxml = array( 'application/zip', 'application/octet-stream' );

		$map = array(
			'pdf'  => array( 'application/pdf', 'application/x-pdf' ),
			'doc'  => $ole,
			'ppt'  => array_merge( $ole, array( 'application/vnd.ms-powerpoint' ) ),
			'xls'  => array_merge( $ole, array( 'application/vnd.ms-excel' ) ),
			'docx' => array_merge( $ooxml, array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ) ),
			'pptx' => array_merge( $ooxml, array( 'application/vnd.openxmlformats-officedocument.presentationml.presentation' ) ),
			'xlsx' => array_merge( $ooxml, array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ) ),
			'odt'  => array_merge( $ooxml, array( 'application/vnd.oasis.opendocument.text' ) ),
			'rtf'  => array( 'application/rtf', 'text/rtf' ),
			'txt'  => array( 'text/plain' ),
			'csv'  => array( 'text/plain', 'text/csv', 'application/csv' ),
			'jpg'  => array( 'image/jpeg' ),
			'jpeg' => array( 'image/jpeg' ),
			'png'  => array( 'image/png' ),
			'gif'  => array( 'image/gif' ),
			'webp' => array( 'image/webp' ),
			'zip'  => array( 'application/zip' ),
			'mp3'  => array( 'audio/mpeg' ),
			'm4a'  => array( 'audio/mp4', 'audio/x-m4a', 'video/mp4' ),
			'wav'  => array( 'audio/x-wav', 'audio/wav' ),
			'ogg'  => array( 'audio/ogg', 'video/ogg', 'application/ogg' ),
			'mp4'  => array( 'video/mp4' ),
			'mov'  => array( 'video/quicktime' ),
			'avi'  => array( 'video/x-msvideo', 'video/avi' ),
			'mpg'  => array( 'video/mpeg' ),
			'wmv'  => array( 'video/x-ms-wmv', 'video/x-ms-asf' ),
		);

		/**
		 * Extension => allowed sniffed MIME types.
		 *
		 * @param array<string,string[]> $map
		 */
		return (array) apply_filters( 'piecyfer/forms/expected_mimes', $map );
	}

	// --------------------------------------------------------------- process

	/**
	 * Move every valid file into the private store and record it on the record.
	 *
	 * @param array<string,mixed> $field
	 */
	public function process_field( $field, FormRecord $record, AjaxHandler $ajax_handler ): void {
		$this->fix_file_indices();

		$id      = (string) $field['id'];
		$files   = $this->field_files( $id );
		$context = $record->get( 'context' );

		foreach ( $files as $index => $file ) {
			if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
				continue;
			}

			// Record what the file actually is, not what the browser said it
			// was: `$file['type']` is client-supplied and Pro never checks it.
			$file['pcf_verified_mime'] = self::sniff_mime(
				(string) ( $file['tmp_name'] ?? '' ),
				(string) ( $file['name'] ?? '' )
			);

			$stored = UploadStore::store(
				$file,
				array(
					'form_id'  => (string) ( $record->get_form_settings( 'id' ) ?? '' ),
					'post_id'  => (int) ( is_array( $context ) ? ( $context['post_id'] ?? 0 ) : 0 ),
					'field_id' => $id,
					'ip'       => Security::client_ip(),
				)
			);

			if ( ! $stored ) {
				$ajax_handler->add_error( $id, esc_html__( 'There was an error while trying to upload your file.', 'piecyfer-core' ) );
				$ajax_handler->add_admin_error_message(
					sprintf(
						/* translators: %s: filesystem path. */
						esc_html__( 'Upload directory is not writable or does not exist: %s', 'piecyfer-core' ),
						UploadStore::configured_dir()
					)
				);

				continue;
			}

			$record->add_file(
				$id,
				(int) $index,
				array(
					'path' => $stored['path'],
					// Pro puts a permanent, public, unauthenticated URL here.
					// This one expires and is signed.
					'url'  => DownloadEndpoint::signed_url( $stored['id'] ),
				)
			);
		}
	}

	/**
	 * `value` becomes the joined URLs, `raw_value` the joined paths.
	 *
	 * Separator is Pro's `' , '` — spaces included (upload.php:547-548) — because
	 * it lands in the email body and in anything reading the record.
	 */
	public function set_file_fields_values( FormRecord $record, AjaxHandler $ajax_handler ): void {
		$files = $record->get( 'files' );

		if ( empty( $files ) ) {
			return;
		}

		foreach ( (array) $files as $id => $files_array ) {
			$record->update_field( (string) $id, 'value', implode( ' , ', (array) $files_array['url'] ) );
			$record->update_field( (string) $id, 'raw_value', implode( ' , ', (array) $files_array['path'] ) );
		}
	}

	// ----------------------------------------------------------------- $_FILES

	/**
	 * Every file posted for one field, as a flat list.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function field_files( string $id ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce policy is Security::nonce_mode().
		$files = isset( $_FILES['form_fields'] ) && is_array( $_FILES['form_fields'] ) ? $_FILES['form_fields'] : array();

		if ( ! isset( $files[ $id ] ) || ! is_array( $files[ $id ] ) ) {
			return array();
		}

		return array_values( $files[ $id ] );
	}

	/**
	 * Rewrite PHP's column-major $_FILES shape into one entry per file.
	 *
	 * Straight from Pro (upload.php:151-184) — the shape is a contract with
	 * anything else reading `$_FILES['form_fields']` during the same request —
	 * with two changes:
	 *
	 *   * it cannot run on a request that has no file part; and
	 *   * "already done" is decided by looking at the array, not by an instance
	 *     flag. Pro's `$fixed_files_indices` is fine for exactly one submission
	 *     per PHP process, which is all a web request ever is — but the field
	 *     object is built once and cached, so the flag survives into the next
	 *     submission handled by the same process (a test harness, a CLI batch,
	 *     anything long-running) and every upload after the first is then
	 *     silently invisible. Checking the shape is both idempotent and free.
	 */
	private function fix_file_indices(): void {
		if ( ! isset( $_FILES['form_fields'] ) || ! is_array( $_FILES['form_fields'] ) ) {
			return;
		}

		$names = array( 'name', 'type', 'tmp_name', 'error', 'size' );
		$files = $_FILES['form_fields']; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- restructured here, validated in validation().

		$needs_fixing = false;

		foreach ( $names as $name ) {
			if ( isset( $files[ $name ] ) && is_array( $files[ $name ] ) ) {
				$needs_fixing = true;
				break;
			}
		}

		if ( ! $needs_fixing ) {
			return;
		}

		foreach ( $files as $key => $part ) {
			$key = (string) $key;

			if ( in_array( $key, $names, true ) && is_array( $part ) ) {
				foreach ( $part as $position => $value ) {
					if ( is_array( $value ) ) {
						foreach ( $value as $index => $inner_val ) {
							$files[ $position ][ $index ][ $key ] = $inner_val;
						}
					} else {
						$files[ $position ][0][ $key ] = $value;
					}
				}

				unset( $files[ $key ] );
			}
		}

		$_FILES['form_fields'] = $files;
	}

	// ----------------------------------------------------------------- limits

	private function default_max_mb(): int {
		$php_ceiling = (int) ( wp_max_upload_size() / ( 1024 ** 2 ) );
		$ceiling     = min( max( 1, $php_ceiling ), self::DEFAULT_MAX_MB );

		/**
		 * Size ceiling, in MB, for an upload field with no `file_sizes` set.
		 *
		 * @param int $mb
		 */
		return max( 1, (int) apply_filters( 'piecyfer/forms/default_max_upload_mb', $ceiling ) );
	}

	private function default_max_files(): int {
		/**
		 * File-count ceiling for an upload field with no `max_files` set.
		 *
		 * @param int $count
		 */
		return max( 1, (int) apply_filters( 'piecyfer/forms/default_max_files', self::DEFAULT_MAX_FILES ) );
	}

	/**
	 * Pro's PHP-upload-error strings (upload.php:308-320), kept verbatim.
	 */
	private function upload_error_message( int $error ): string {
		$messages = array(
			UPLOAD_ERR_OK        => esc_html__( 'There is no error, the file uploaded with success.', 'piecyfer-core' ),
			UPLOAD_ERR_INI_SIZE  => sprintf(
				/* translators: 1: upload_max_filesize, 2: php.ini */
				esc_html__( 'The uploaded file exceeds the %1$s directive in %2$s.', 'piecyfer-core' ),
				'upload_max_filesize',
				'php.ini'
			),
			UPLOAD_ERR_FORM_SIZE => sprintf(
				/* translators: %s: MAX_FILE_SIZE */
				esc_html__( 'The uploaded file exceeds the %s directive that was specified in the HTML form.', 'piecyfer-core' ),
				'MAX_FILE_SIZE'
			),
			UPLOAD_ERR_PARTIAL   => esc_html__( 'The uploaded file was only partially uploaded.', 'piecyfer-core' ),
			UPLOAD_ERR_NO_FILE   => esc_html__( 'No file was uploaded.', 'piecyfer-core' ),
			UPLOAD_ERR_NO_TMP_DIR => esc_html__( 'Missing a temporary folder.', 'piecyfer-core' ),
			UPLOAD_ERR_CANT_WRITE => esc_html__( 'Failed to write file to disk.', 'piecyfer-core' ),
			UPLOAD_ERR_EXTENSION => esc_html__( 'A PHP extension stopped the file upload.', 'piecyfer-core' ),
		);

		return $messages[ $error ] ?? esc_html__( 'There was an error while trying to upload your file.', 'piecyfer-core' );
	}

	private function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[piecyfer-forms/upload] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
