<?php
/**
 * The public form submit endpoint.
 *
 * Replaces ElementorPro\Modules\Forms\Classes\Ajax_Handler
 * (elementor-pro/modules/forms/classes/ajax-handler.php). Same action string,
 * same POST contract, byte-compatible JSON response — the frontend JS, whether
 * it is Pro's bundle or its replacement, must not be able to tell the
 * difference. See 06-FORM-SPEC.md §1.
 *
 * The public surface (`$is_success`, `$messages`, `$data`, `$errors`,
 * `add_error()`, `add_error_message()`, `add_admin_error_message()`,
 * `add_response_data()`, `add_success_message()`, `set_success()`, `send()`,
 * `get_current_form()`) is Pro's, because form actions, field validators and
 * third-party listeners all receive this object and call into it.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms;

defined( 'ABSPATH' ) || exit;

class AjaxHandler {

	/**
	 * Pro's action string, kept exactly: the markup and the JS both name it.
	 */
	public const ACTION = 'elementor_pro_forms_send_form';

	/** @var bool */
	public $is_success = true;

	/** @var array{success:string[],error:string[],admin_error:string[]} */
	public $messages = array(
		'success'     => array(),
		'error'       => array(),
		'admin_error' => array(),
	);

	/** @var array<string,mixed> */
	public $data = array();

	/** @var array<string,string> */
	public $errors = array();

	/** @var array<string,mixed>|null */
	private $current_form = null;

	/** @var int */
	private $post_id = 0;

	/** @var bool Set once switch_to_post() has run, so run() can undo it. */
	private $switched = false;

	// ------------------------------------------------------------- messages

	/**
	 * BC shim: Pro's field classes and third-party validators call
	 * `Ajax_Handler::get_default_message( Ajax_Handler::FIELD_REQUIRED, $settings )`.
	 *
	 * @param string              $id
	 * @param array<string,mixed> $settings
	 */
	public static function get_default_message( $id, $settings ): string {
		return Messages::get( (string) $id, (array) $settings );
	}

	/**
	 * @return array<string,string>
	 */
	public static function get_default_messages(): array {
		return Messages::defaults();
	}

	// -------------------------------------------------------------- entries

	/**
	 * The wp_ajax callback. Emits JSON and exits, exactly like Pro.
	 */
	public function ajax_send_form(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce policy is Security::nonce_mode(); see 06-FORM-SPEC.md §1.3.
		$response = $this->run( $_POST, $_FILES );

		if ( $response['success'] ) {
			wp_send_json_success( $response['payload'] );
		}

		wp_send_json_error( $response['payload'] );
	}

	/**
	 * The whole pipeline, without emitting anything.
	 *
	 * Returns `array{ success: bool, payload: array }` where `payload` is exactly
	 * what Pro wraps in `wp_send_json_success()` / `wp_send_json_error()`. Split
	 * out from ajax_send_form() so the pipeline can be exercised end to end from
	 * a harness without `wp_die()` taking the process with it.
	 *
	 * NOTE: `$post` is the **raw, still-slashed** POST array, as Pro passes it
	 * (ajax-handler.php:62). Slashes come off exactly once, inside FormRecord's
	 * constructor (`stripslashes_deep`, form-record.php:342). Unslashing here as
	 * well would eat a real backslash out of every value that contains one.
	 *
	 * @param array<string,mixed> $post
	 * @param array<string,mixed> $files
	 *
	 * @return array{success:bool,payload:array<string,mixed>}
	 */
	public function run( array $post, array $files = array() ): array {
		try {
			$this->pipeline( $post, $files );
			$this->send();
		} catch ( HaltSubmission $halt ) {
			// Normal control flow: send() always throws.
			unset( $halt );
		} catch ( \Throwable $e ) {
			/*
			 * DIVERGENCE FROM PRO. An uncaught throwable in Pro's handler is a
			 * PHP fatal: admin-ajax returns 500 with an HTML body, and the JS
			 * error path shows the visitor jQuery's "parsererror". Here it
			 * becomes a well-formed error response, so the visitor is told to
			 * try again instead of being shown a broken widget, and the detail
			 * goes to the log.
			 */
			$this->log( 'unhandled exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );

			$this->add_error_message( Messages::get( Messages::SERVER_ERROR, $this->form_settings() ) );
			$this->add_admin_error_message( $e->getMessage() );
		}

		$this->restore_post();

		return $this->build_response();
	}

	// ------------------------------------------------------------- pipeline

	/**
	 * @param array<string,mixed> $post
	 * @param array<string,mixed> $files
	 *
	 * @throws HaltSubmission When any step decides the request is over.
	 */
	private function pipeline( array $post, array $files ): void {
		$post_id = isset( $post['post_id'] ) && is_scalar( $post['post_id'] ) ? absint( $post['post_id'] ) : 0;
		$form_id = isset( $post['form_id'] ) && is_scalar( $post['form_id'] ) ? (string) $post['form_id'] : '';

		$this->post_id = $post_id;

		// Elementor element ids are short alphanumerics. Anything else is not a
		// form id, and letting it through only widens what find_element() sees.
		if ( ! $post_id || '' === $form_id || ! preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $form_id ) ) {
			$this->add_error_message( Messages::get( Messages::INVALID_FORM, array() ) )->send();
		}

		if ( ! Security::nonce_ok() ) {
			/*
			 * A stale nonce from a cached page is indistinguishable from a
			 * forged one, so the message has to make sense to a human: reload
			 * and try again.
			 */
			$this->add_error_message( esc_html__( 'This form has expired. Please reload the page and try again.', 'piecyfer-core' ) )->send();
		}

		if ( RateLimiter::too_many( $form_id ) ) {
			$this->log( 'rate limited: ' . Security::client_ip() . ' form ' . $form_id );
			$this->add_error_message( RateLimiter::message() )->send();
		}

		$form = $this->find_form( $post_id, $form_id );

		// Resolve dynamic values against the post the visitor was actually on,
		// not against whatever id the client asked us to switch to (§7).
		$queried_id = Security::resolve_queried_id( $post['queried_id'] ?? null, $post_id );

		$elementor = \Elementor\Plugin::$instance;

		if ( isset( $elementor->db ) ) {
			$elementor->db->switch_to_post( $queried_id );
			$this->switched = true;
		}

		$template_id = $form['_pcf_template_id'] ?? null;
		unset( $form['_pcf_template_id'] );

		$widget = $elementor->elements_manager->create_element_instance( $form );

		if ( ! $widget ) {
			$this->log( "could not instantiate widget for form {$form_id} on post {$post_id}" );
			$this->add_error_message( Messages::get( Messages::INVALID_FORM, array() ) )->send();
		}

		/*
		 * This is the line that makes an absent `submit_actions` resolve to the
		 * control default — `['email']` — rather than to nothing at all. See
		 * 06-FORM-SPEC.md §3.2: the saved data on all nine forms has no
		 * `submit_actions` key, and `Base_Data::get_value()` tests `isset()`.
		 */
		$form['settings'] = $widget->get_settings_for_display();

		// Pro overwrites these three after resolving settings (ajax-handler.php:107-111);
		// actions and third-party listeners read them.
		$form['settings']['id']           = $form_id;
		$form['settings']['form_post_id'] = $template_id ?: $post_id;
		$form['settings']['edit_post_id'] = $post_id;

		$this->current_form = $form;

		if ( empty( $form['settings']['form_fields'] ) ) {
			$this->add_error_message( Messages::get( Messages::INVALID_FORM, $form['settings'] ) )->send();
		}

		$record = new FormRecord(
			$post['form_fields'] ?? array(),
			$form,
			array(
				'post_id'    => $post_id,
				'queried_id' => $queried_id,
				'referrer'   => Security::referrer_url(),
				'files'      => $files,
			)
		);

		if ( ! $record->validate( $this ) ) {
			$this->add_error_message( Messages::get( Messages::ERROR, $form['settings'] ) )->send();
		}

		$record->process_fields( $this );

		if ( ! empty( $this->errors ) ) {
			$this->send();
		}

		$this->dispatch( $record, $form );

		/** This action mirrors elementor_pro/forms/new_record — see Hooks. */
		Hooks::do_action( 'new_record', $record, $this );
	}

	/**
	 * Locate the form element inside the document that claims to hold it.
	 *
	 * Two checks Pro does not do (§1.4):
	 *
	 *  1. **The element must be a `form` widget.** Pro accepts any element id in
	 *     any document `documents->get()` will return, then instantiates it and
	 *     runs form actions against its settings. Since `post_id` and `form_id`
	 *     are both public in the page HTML, that is a lot of trust for free.
	 *  2. **The document must be one the public can see** — or the requester must
	 *     be able to edit it. Otherwise a draft page containing a form with an
	 *     attacker-chosen `email_to` is a mail relay on our domain.
	 *
	 * @return array<string,mixed>
	 *
	 * @throws HaltSubmission When the form cannot be located or is not a form.
	 */
	private function find_form( int $post_id, string $form_id ): array {
		$elementor = \Elementor\Plugin::$instance;
		$document  = $elementor->documents->get( $post_id );

		if ( ! $document ) {
			$this->add_error_message( Messages::get( Messages::INVALID_FORM, array() ) )->send();
		}

		if ( ! $this->document_is_public( $post_id ) ) {
			$this->log( "refused submission against non-public post {$post_id}" );
			$this->add_error_message( Messages::get( Messages::INVALID_FORM, array() ) )->send();
		}

		$form        = self::find_element_recursive( $document->get_elements_data(), $form_id );
		$template_id = null;

		// Global-widget indirection, as Pro does it (ajax-handler.php:87-96).
		if ( ! empty( $form['templateID'] ) ) {
			$template = $elementor->documents->get( $form['templateID'] );

			if ( ! $template ) {
				$this->add_error_message( Messages::get( Messages::INVALID_FORM, array() ) )->send();
			}

			$template_id = $template->get_id();
			$elements    = $template->get_elements_data();
			$form        = $elements[0] ?? false;
		}

		if ( empty( $form ) || ! is_array( $form ) ) {
			$this->add_error_message( Messages::get( Messages::INVALID_FORM, array() ) )->send();
		}

		if ( 'widget' !== ( $form['elType'] ?? '' ) || 'form' !== ( $form['widgetType'] ?? '' ) ) {
			$this->log( "element {$form_id} on post {$post_id} is not a form widget" );
			$this->add_error_message( Messages::get( Messages::INVALID_FORM, array() ) )->send();
		}

		if ( $template_id ) {
			$form['_pcf_template_id'] = $template_id;
		}

		return $form;
	}

	/**
	 * Is this document one a visitor could legitimately have seen the form on?
	 *
	 * Theme-builder templates and popups are `elementor_library` posts, which are
	 * published but not publicly queryable — hence the status test rather than a
	 * `is_post_publicly_viewable()` test, which would reject every footer form on
	 * the site.
	 *
	 * KNOWN LIVE EFFECT — read this before enabling the module. All five
	 * job-application pages (991318, 991362, 991364, 991372, 995936) are
	 * currently `draft`. A logged-out submission naming one of them is refused
	 * here, where Pro would have accepted it. A draft page is not publicly
	 * viewable, so in principle nobody can be looking at that form — but if those
	 * pages are being surfaced some other way, this turns working forms off.
	 * Check before the cut-over, and use the filter below if the answer is that
	 * they really are in use while unpublished.
	 */
	private function document_is_public( int $post_id ): bool {
		/**
		 * Post statuses a form may be submitted from without being logged in.
		 *
		 * @param string[] $statuses
		 * @param int      $post_id
		 */
		$statuses = (array) apply_filters(
			'piecyfer/forms/allowed_post_statuses',
			array( 'publish', 'private', 'inherit' ),
			$post_id
		);

		if ( in_array( get_post_status( $post_id ), $statuses, true ) ) {
			return true;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Depth-first id match. Same semantics as Module::find_element_recursive()
	 * (elementor-pro/modules/forms/module.php:92-108).
	 *
	 * @param array<int,array<string,mixed>> $elements
	 *
	 * @return array<string,mixed>|false
	 */
	public static function find_element_recursive( array $elements, string $form_id ) {
		foreach ( $elements as $element ) {
			if ( $form_id === ( $element['id'] ?? null ) ) {
				return $element;
			}

			if ( ! empty( $element['elements'] ) ) {
				$found = self::find_element_recursive( (array) $element['elements'], $form_id );

				if ( $found ) {
					return $found;
				}
			}
		}

		return false;
	}

	/**
	 * Run every registered action that this form asks for.
	 *
	 * Iteration order is the registrar's, not `submit_actions`', exactly as Pro
	 * does it (ajax-handler.php:151) — so when a submissions store is added later
	 * it can register ahead of `email` and record the enquiry even if the mail
	 * then fails.
	 *
	 * @param array<string,mixed> $form
	 *
	 * @throws HaltSubmission Propagated from an action that called send().
	 */
	private function dispatch( FormRecord $record, array $form ): void {
		$submit_actions = (array) ( $form['settings']['submit_actions'] ?? array() );
		$errors         = array_merge( $this->messages['error'], $this->messages['admin_error'] );

		/** This filter mirrors elementor_pro/forms/record/actions_before — see Hooks. */
		$record = Hooks::apply_filters( 'record/actions_before', $record, $this );

		foreach ( Module::actions() as $action ) {
			if ( ! in_array( $action->get_name(), $submit_actions, true ) ) {
				continue;
			}

			$exception = null;

			try {
				$action->run( $record, $this );
				$this->handle_bc_errors( $errors );
			} catch ( HaltSubmission $halt ) {
				throw $halt;
			} catch ( \Exception $e ) {
				$exception = $e;

				if ( ! in_array( $exception->getMessage(), $this->messages['admin_error'], true ) ) {
					$this->add_admin_error_message( "{$action->get_label()} {$exception->getMessage()}" );
				}

				$this->add_error_message( Messages::get( Messages::ERROR, $this->form_settings() ) );
			}

			$errors = array_merge( $this->messages['error'], $this->messages['admin_error'] );

			/** This action mirrors elementor_pro/forms/actions/after_run — see Hooks. */
			Hooks::do_action( 'actions/after_run', $action, $exception );
		}
	}

	/**
	 * Legacy actions report failure by adding a message rather than throwing.
	 * Verbatim from ajax-handler.php:297-304.
	 *
	 * @param string[] $errors
	 *
	 * @throws \Exception When the action added anything new.
	 */
	private function handle_bc_errors( array $errors ): void {
		$current_errors = array_merge( $this->messages['error'], $this->messages['admin_error'] );
		$errors_diff    = array_diff( $current_errors, $errors );

		if ( count( $errors_diff ) > 0 ) {
			throw new \Exception( esc_html( implode( ', ', $errors_diff ) ) );
		}
	}

	// ------------------------------------------------------------- collection

	/**
	 * @param string $message
	 */
	public function add_success_message( $message ): self {
		$this->messages['success'][] = (string) $message;

		return $this;
	}

	/**
	 * @param string $key
	 * @param mixed  $data
	 */
	public function add_response_data( $key, $data ): self {
		$this->data[ $key ] = $data;

		return $this;
	}

	/**
	 * @param string $message
	 */
	public function add_error_message( $message ): self {
		$this->messages['error'][] = (string) $message;
		$this->set_success( false );

		return $this;
	}

	/**
	 * Add a field-keyed error, or merge a whole map of them.
	 *
	 * Pro's array branch uses `+=` (ajax-handler.php:235), so for an existing key
	 * the *first* error wins and later ones are dropped; the scalar branch
	 * overwrites. Kept exactly — the upload validator relies on it to report one
	 * problem per field rather than a stack of them.
	 *
	 * @param string|array<string,string> $field
	 * @param string                      $message
	 */
	public function add_error( $field, $message = '' ): self {
		if ( is_array( $field ) ) {
			$this->errors += $field;
		} else {
			$this->errors[ (string) $field ] = (string) $message;
		}

		$this->set_success( false );

		return $this;
	}

	/**
	 * @param string $message
	 */
	public function add_admin_error_message( $message ): self {
		$this->messages['admin_error'][] = (string) $message;
		$this->set_success( false );

		return $this;
	}

	/**
	 * @param bool $bool
	 */
	public function set_success( $bool ): self {
		$this->is_success = (bool) $bool;

		return $this;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get_current_form() {
		return $this->current_form;
	}

	/**
	 * Stop the pipeline and answer the browser.
	 *
	 * @throws HaltSubmission Always.
	 */
	public function send(): void {
		throw new HaltSubmission();
	}

	// -------------------------------------------------------------- response

	/**
	 * The exact payload shape Pro sends (§1.5). Both branches are HTTP 200.
	 *
	 * @return array{success:bool,payload:array<string,mixed>}
	 */
	private function build_response(): array {
		if ( $this->is_success ) {
			return array(
				'success' => true,
				'payload' => array(
					'message' => Messages::get( Messages::SUCCESS, $this->form_settings() ),
					'data'    => $this->data,
				),
			);
		}

		if ( empty( $this->messages['error'] ) && ! empty( $this->errors ) ) {
			$this->add_error_message( Messages::get( Messages::INVALID_FORM, $this->form_settings() ) );
		}

		$error_msg = implode( '<br>', $this->messages['error'] );

		/*
		 * Admin-only leak channel, kept with its capability check intact
		 * (ajax-handler.php:273-276). Everything in `admin_error` is internal —
		 * SMTP failures, "upload directory is not writable", exception messages —
		 * and it is only ever appended for a user who can edit the post the form
		 * lives on.
		 */
		if ( ! empty( $this->messages['admin_error'] ) && current_user_can( 'edit_post', $this->post_id ) ) {
			$this->messages['admin_error'][] = esc_html__( 'This message is not visible to site visitors.', 'piecyfer-core' );

			$error_msg .= '<div class="elementor-forms-admin-errors">' . implode( '<br>', $this->messages['admin_error'] ) . '</div>';
		}

		return array(
			'success' => false,
			'payload' => array(
				'message' => $error_msg,
				'errors'  => $this->errors,
				'data'    => $this->data,
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function form_settings(): array {
		return (array) ( $this->current_form['settings'] ?? array() );
	}

	private function restore_post(): void {
		if ( ! $this->switched ) {
			return;
		}

		$elementor = \Elementor\Plugin::$instance;

		if ( isset( $elementor->db ) ) {
			// Pro never restores; leaving the global post switched for the rest
			// of the request is how a submission ends up affecting whatever runs
			// after it on the same PHP process.
			$elementor->db->restore_current_post();
		}

		$this->switched = false;
	}

	private function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[piecyfer-forms] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
