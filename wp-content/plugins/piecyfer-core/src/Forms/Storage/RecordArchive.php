<?php
/**
 * A last-resort copy of every accepted submission.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS EXISTS
 * ---------------------------------------------------------------------------
 * Today two things happen on every submission: an email goes out, and Pro's
 * Submissions component writes a row to `wp_e_submissions` (317 of them so far).
 * The second one is a Pro feature with a React admin screen, a REST controller,
 * a GDPR exporter and a CSV export behind it, and it is **not** part of this
 * work — see 06-FORM-SPEC.md §9.2 item 6, which is explicit that whether new
 * submissions get recorded at all is a decision somebody has to make before the
 * cut-over.
 *
 * If that decision is deferred, the failure mode is silent: `wp_mail()` returns
 * false, or the mail is accepted and then dropped by a spam filter, and the
 * enquiry simply never existed. For a site whose forms carry job applications
 * and sales enquiries that is the worst outcome available.
 *
 * So: an append-only JSONL file per month in the private upload directory,
 * written for every submission that got as far as running its actions. It is not
 * a submissions system — there is no UI, no search, no export. It is a receipt,
 * so that "did we lose anything?" has an answer.
 *
 * It records form values, which means it records personal data. It is covered by
 * the same retention sweep as the uploads.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Storage;

use PieCyfer\Core\Forms\AjaxHandler;
use PieCyfer\Core\Forms\FormRecord;
use PieCyfer\Core\Forms\Security;

defined( 'ABSPATH' ) || exit;

final class RecordArchive {

	public static function register(): void {
		/**
		 * Keep a local copy of every accepted submission.
		 *
		 * @param bool $enabled
		 */
		if ( ! apply_filters( 'piecyfer/forms/archive_records', true ) ) {
			return;
		}

		add_action( 'piecyfer/forms/new_record', array( self::class, 'write' ), 100, 2 );
	}

	/**
	 * Append one line of JSON.
	 *
	 * Priority 100 so it runs after everything else listening on `new_record`,
	 * and after the dispatcher has finished, which means `$ajax_handler->is_success`
	 * already reflects whether the Email action worked.
	 */
	public static function write( FormRecord $record, AjaxHandler $ajax_handler ): void {
		try {
			$dir = UploadStore::dir();

			if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
				return;
			}

			$settings = (array) $record->get( 'form_settings' );

			$line = wp_json_encode(
				array(
					'time'      => gmdate( 'c' ),
					'form_id'   => (string) ( $settings['id'] ?? '' ),
					'form_name' => (string) ( $settings['form_name'] ?? '' ),
					'post_id'   => (int) ( $settings['edit_post_id'] ?? 0 ),
					'mail_ok'   => (bool) $ajax_handler->is_success,
					'errors'    => $ajax_handler->messages['admin_error'],
					'ip'        => Security::client_ip(),
					'fields'    => $record->get_formatted_data( true ),
					'files'     => array_keys( (array) $record->get( 'files' ) ),
					'expires'   => time() + ( UploadStore::retention_days() * DAY_IN_SECONDS ),
				)
			);

			$path = trailingslashit( $dir ) . 'submissions-' . gmdate( 'Y-m' ) . '.jsonl';

			file_put_contents( $path, $line . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
			@chmod( $path, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} catch ( \Throwable $e ) {
			// A failure to write the receipt must never fail the submission.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[piecyfer-forms/archive] ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	/**
	 * Delete archive files whose whole month is past the retention window.
	 *
	 * @return int Files removed.
	 */
	public static function gc(): int {
		$dir = UploadStore::configured_dir();

		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$removed = 0;
		$cutoff  = time() - ( UploadStore::retention_days() * DAY_IN_SECONDS );

		foreach ( (array) glob( trailingslashit( $dir ) . 'submissions-*.jsonl' ) as $path ) {
			if ( ! preg_match( '/submissions-(\d{4})-(\d{2})\.jsonl$/', (string) $path, $m ) ) {
				continue;
			}

			// End of that month, so a file is only removed once every line in it
			// is past retention.
			$month_end = (int) gmmktime( 0, 0, 0, ( (int) $m[2] ) + 1, 1, (int) $m[1] );

			if ( $month_end > $cutoff ) {
				continue;
			}

			wp_delete_file( (string) $path );
			++$removed;
		}

		return $removed;
	}
}
