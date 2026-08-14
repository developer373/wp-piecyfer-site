<?php
/**
 * Registration glue for the form back end.
 *
 * ===========================================================================
 * THIS IS NOT SWITCHED ON. READ BEFORE ENABLING.
 * ===========================================================================
 * Nothing in `src/Forms/` runs until two things are true:
 *
 *   1. `Plugin::__construct()` calls `Forms\Module::init()` — it does not, and
 *      wiring it is the lead's call, not this branch's; and
 *   2. the master switch is on:
 *
 *          define( 'PIECYFER_FORMS_ENABLED', true );      // wp-config.php
 *      or  add_filter( 'piecyfer/forms/enabled', '__return_true' );
 *
 * Even with both done, {@see self::pro_forms_active()} refuses to register the
 * AJAX endpoint while Elementor Pro's forms module is loaded. Two handlers on
 * `wp_ajax_elementor_pro_forms_send_form` would both run, and **every
 * submission would send two emails** — one from Pro, one from us. Override only
 * with `PIECYFER_FORMS_FORCE_TAKEOVER`, and only after unhooking Pro's.
 *
 * ---------------------------------------------------------------------------
 * CUT-OVER ORDER
 * ---------------------------------------------------------------------------
 *   1. Run {@see self::preflight()} and read every line of it. In particular:
 *      `upload_dir_outside_web_root` must be true, and `recaptcha_v3_configured`
 *      must be true.
 *   2. Verify the reCAPTCHA v3 key pair in the Google console. All four
 *      reCAPTCHA options on this install share the `6LcmQX` prefix, which is
 *      what one key pasted into four boxes looks like. See {@see Recaptcha\V3Handler}.
 *   3. Decide whether new submissions are recorded anywhere other than email
 *      (see {@see Storage\RecordArchive}, and §9.2 item 6 of the spec).
 *   4. Enable this module, deactivate Pro's forms, submit all nine forms.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms;

use PieCyfer\Core\Forms\Actions\ActionBase;
use PieCyfer\Core\Forms\Actions\Email2Action;
use PieCyfer\Core\Forms\Actions\EmailAction;
use PieCyfer\Core\Forms\Fields\AcceptanceField;
use PieCyfer\Core\Forms\Fields\DateField;
use PieCyfer\Core\Forms\Fields\EmailField;
use PieCyfer\Core\Forms\Fields\FieldBase;
use PieCyfer\Core\Forms\Fields\NumberField;
use PieCyfer\Core\Forms\Fields\TelField;
use PieCyfer\Core\Forms\Fields\TimeField;
use PieCyfer\Core\Forms\Fields\UploadField;
use PieCyfer\Core\Forms\Recaptcha\V3Handler;
use PieCyfer\Core\Forms\Storage\DownloadEndpoint;
use PieCyfer\Core\Forms\Storage\RecordArchive;
use PieCyfer\Core\Forms\Storage\UploadStore;

defined( 'ABSPATH' ) || exit;

final class Module {

	public const GC_HOOK = 'piecyfer_forms_gc';

	/** @var ActionBase[]|null */
	private static ?array $actions = null;

	/** @var FieldBase[]|null */
	private static ?array $fields = null;

	/** @var bool */
	private static bool $registered = false;

	/**
	 * The one entry point. Safe to call unconditionally: it is a no-op unless
	 * the master switch is on.
	 */
	public static function init(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Priority 20 on `init`: after Elementor (`elementor/init` runs at
		// `init` priority 0) so that Pro's registrations are visible to the
		// interlock below, and well before admin-ajax dispatches.
		add_action( 'init', array( self::class, 'register' ), 20 );
	}

	/**
	 * The master switch. Default: **off**.
	 */
	public static function is_enabled(): bool {
		if ( defined( 'PIECYFER_FORMS_ENABLED' ) ) {
			return (bool) constant( 'PIECYFER_FORMS_ENABLED' );
		}

		/**
		 * Turn the PieCyfer form back end on.
		 *
		 * @param bool $enabled
		 */
		return (bool) apply_filters( 'piecyfer/forms/enabled', false );
	}

	/**
	 * Hook everything up.
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		if ( self::pro_forms_active() && ! self::force_takeover() ) {
			self::log( 'Elementor Pro forms is live — standing down so submissions are not handled twice.' );

			return;
		}

		self::$registered = true;

		$handler = new AjaxHandler();

		add_action( 'wp_ajax_' . AjaxHandler::ACTION, array( $handler, 'ajax_send_form' ) );
		add_action( 'wp_ajax_nopriv_' . AjaxHandler::ACTION, array( $handler, 'ajax_send_form' ) );

		foreach ( self::fields() as $field ) {
			$field->register();
		}

		( new V3Handler() )->register();

		DownloadEndpoint::register();
		RecordArchive::register();

		if ( apply_filters( 'piecyfer/forms/inject_nonce', false ) ) {
			add_filter( 'elementor/widget/render_content', array( self::class, 'inject_nonce_field' ), 10, 2 );
		}

		self::schedule_gc();
		add_action( self::GC_HOOK, array( self::class, 'run_gc' ) );
	}

	/**
	 * Is Elementor Pro still handling form submissions?
	 *
	 * Checked two ways because either alone can be wrong: the hook only exists
	 * on a request Pro recognised as a submission (its handler is constructed
	 * lazily, forms/module.php:304-305), and the class check only works once
	 * something has loaded it.
	 */
	public static function pro_forms_active(): bool {
		if ( has_action( 'wp_ajax_' . AjaxHandler::ACTION ) || has_action( 'wp_ajax_nopriv_' . AjaxHandler::ACTION ) ) {
			return true;
		}

		return class_exists( '\ElementorPro\Modules\Forms\Module', false );
	}

	private static function force_takeover(): bool {
		if ( defined( 'PIECYFER_FORMS_FORCE_TAKEOVER' ) ) {
			return (bool) constant( 'PIECYFER_FORMS_FORCE_TAKEOVER' );
		}

		/**
		 * Register even though Pro's forms module is loaded.
		 *
		 * Only ever true when something has already unhooked Pro's handler.
		 *
		 * @param bool $force
		 */
		return (bool) apply_filters( 'piecyfer/forms/force_takeover', false );
	}

	/**
	 * Submit actions, in the order they run.
	 *
	 * Order is the registrar's, not the form's `submit_actions` order — Pro
	 * iterates the registry and skips anything not selected (ajax-handler.php:151).
	 * Pro's own Submissions component registers `save-to-database` at priority 0
	 * so it lands *before* `email`; if a submissions store is ever built, it goes
	 * at the front of this list for the same reason: an enquiry should be
	 * recorded even when the mail then fails.
	 *
	 * Not implemented (their controls exist in FormWidget and their saved values
	 * round-trip, but nothing runs): `save-to-database`, `redirect`, `webhook`,
	 * `popup`, and the six ESP integrations. None is selected on any of the nine
	 * live forms.
	 *
	 * @return ActionBase[]
	 */
	public static function actions(): array {
		if ( null === self::$actions ) {
			/**
			 * Registered submit actions.
			 *
			 * @param ActionBase[] $actions
			 */
			self::$actions = (array) apply_filters(
				'piecyfer/forms/actions',
				array(
					new EmailAction(),
					new Email2Action(),
				)
			);
		}

		return self::$actions;
	}

	/**
	 * @return FieldBase[]
	 */
	public static function fields(): array {
		if ( null === self::$fields ) {
			/**
			 * Registered field types with server-side behaviour.
			 *
			 * @param FieldBase[] $fields
			 */
			self::$fields = (array) apply_filters(
				'piecyfer/forms/fields',
				array(
					new TelField(),
					new EmailField(),
					new NumberField(),
					new TimeField(),
					new DateField(),
					new AcceptanceField(),
					new UploadField(),
				)
			);
		}

		return self::$fields;
	}

	/**
	 * Splice the nonce field into the rendered form.
	 *
	 * Off by default. FormWidget is owned by another branch and its markup is
	 * verified byte-for-byte against Pro's, so adding an input from here would
	 * fail that check the moment the module is enabled during a comparison run.
	 * The tidy version of this is one line in FormWidget::render_widget();
	 * this exists so the nonce can be turned on without waiting for it.
	 *
	 * @param string $content The widget's rendered HTML.
	 * @param mixed  $widget  The widget instance.
	 */
	public static function inject_nonce_field( $content, $widget ): string {
		$content = (string) $content;

		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'form' !== $widget->get_name() ) {
			return $content;
		}

		if ( false !== strpos( $content, Security::NONCE_FIELD ) ) {
			return $content;
		}

		$anchor = '<input type="hidden" name="form_id"';
		$at     = strpos( $content, $anchor );

		if ( false === $at ) {
			return $content;
		}

		$end = strpos( $content, '>', $at );

		if ( false === $end ) {
			return $content;
		}

		return substr_replace( $content, Security::nonce_field(), $end + 1, 0 );
	}

	// ------------------------------------------------------------------ cron

	/**
	 * Daily retention sweep. Scheduled only while the module is enabled, so
	 * nothing is added to the cron option by merely having these files on disk.
	 */
	private static function schedule_gc(): void {
		if ( ! wp_next_scheduled( self::GC_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::GC_HOOK );
		}
	}

	public static function run_gc(): void {
		$files   = UploadStore::gc();
		$archive = RecordArchive::gc();

		self::log( "retention sweep removed {$files} upload(s) and {$archive} archive file(s)" );
	}

	/**
	 * Clear the scheduled sweep. Call from plugin deactivation if this module is
	 * ever wired into one.
	 */
	public static function unschedule_gc(): void {
		$timestamp = wp_next_scheduled( self::GC_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::GC_HOOK );
		}
	}

	// ------------------------------------------------------------- preflight

	/**
	 * Everything worth knowing before the switch is flipped. Read-only.
	 *
	 * @return array<string,mixed>
	 */
	public static function preflight(): array {
		$store = UploadStore::preflight();

		return array(
			'enabled'                      => self::is_enabled(),
			'registered'                   => self::$registered,
			'pro_forms_active'             => self::pro_forms_active(),
			'force_takeover'               => self::force_takeover(),
			'nonce_mode'                   => Security::nonce_mode(),
			'nonce_injected'               => (bool) apply_filters( 'piecyfer/forms/inject_nonce', false ),
			'rate_limit_enabled'           => (bool) apply_filters( 'piecyfer/forms/rate_limit/enabled', true ),
			'actions'                      => array_map(
				static function ( ActionBase $action ): string {
					return $action->get_name();
				},
				self::actions()
			),
			'field_types'                  => array_map(
				static function ( FieldBase $field ): string {
					return $field->get_type();
				},
				self::fields()
			),
			'recaptcha_v3_configured'      => V3Handler::is_enabled(),
			'recaptcha_v3_threshold'       => V3Handler::get_threshold(),
			'recaptcha_fail_open'          => (bool) apply_filters( 'piecyfer/forms/recaptcha/fail_open', true ),
			'upload_dir'                   => $store['dir'],
			'upload_dir_exists'            => $store['exists'],
			'upload_dir_writable'          => $store['writable'],
			'upload_dir_outside_web_root'  => $store['outside_web_root'],
			'upload_retention_days'        => $store['retention_days'],
			'legacy_pro_upload_dir'        => $store['legacy_pro_dir'],
			'archive_records'              => (bool) apply_filters( 'piecyfer/forms/archive_records', true ),
			'gc_scheduled'                 => (bool) wp_next_scheduled( self::GC_HOOK ),
		);
	}

	private static function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[piecyfer-forms] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
