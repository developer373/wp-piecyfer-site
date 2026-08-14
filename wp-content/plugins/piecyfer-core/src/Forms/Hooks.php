<?php
/**
 * Hook naming, and why there are two sets of names.
 *
 * ---------------------------------------------------------------------------
 * THE PROBLEM
 * ---------------------------------------------------------------------------
 * 06-FORM-SPEC.md §9.3 asks the new handler to keep firing the
 * `elementor_pro/forms/*` hooks so third-party listeners keep working. That is
 * right, and it is what happens — but it cannot be done unconditionally, and
 * finding out why cost a fatal error in the test harness:
 *
 *     Uncaught TypeError: Number::validation(): Argument #2 ($record) must be of
 *     type ElementorPro\Modules\Forms\Classes\Form_Record,
 *     PieCyfer\Core\Forms\FormRecord given
 *
 * Pro's own field classes are hooked onto those names with **typed parameters**
 * (fields/number.php:79, fields/tel.php:30, fields/upload.php:304 …). Hand them
 * our record object and PHP throws, and a TypeError inside `do_action()` aborts
 * every remaining callback on that hook. So a pipeline that fires
 * `elementor_pro/forms/validation/tel` while Elementor Pro is still installed
 * fails outright — which is exactly the state the site is in during the
 * transition, and exactly when it must not.
 *
 * ---------------------------------------------------------------------------
 * THE RULE
 * ---------------------------------------------------------------------------
 *  * `piecyfer/forms/<name>` always fires. It is our own contract, and it is
 *    what our field classes, reCAPTCHA handler and archive listen on.
 *  * `elementor_pro/forms/<name>` fires **only when Pro's form classes are not
 *    loaded** — i.e. after the cut-over, which is the only time a listener
 *    could not be type-hinting a Pro class that no longer exists.
 *
 * So third-party integrations keep working after Pro is removed, nothing
 * double-runs while it is still there, and the transitional state is safe in
 * both directions.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms;

defined( 'ABSPATH' ) || exit;

final class Hooks {

	/**
	 * Are Pro's form classes in memory?
	 *
	 * `false` for the autoload flag on purpose: the question is whether they are
	 * already loaded, not whether they could be. Asking with autoload would load
	 * Pro's classes just to find out.
	 */
	public static function pro_classes_loaded(): bool {
		return class_exists( '\ElementorPro\Modules\Forms\Classes\Form_Record', false )
			|| class_exists( '\ElementorPro\Modules\Forms\Classes\Ajax_Handler', false );
	}

	/**
	 * @param string $name Hook suffix, e.g. `validation/tel`.
	 * @param mixed  ...$args
	 */
	public static function do_action( string $name, ...$args ): void {
		do_action( 'piecyfer/forms/' . $name, ...$args );

		if ( ! self::pro_classes_loaded() ) {
			/**
			 * Compatibility alias for the matching `elementor_pro/forms/*` hook.
			 *
			 * Fired only once Elementor Pro's form classes are gone. See the
			 * class docblock.
			 */
			do_action( 'elementor_pro/forms/' . $name, ...$args );
		}
	}

	/**
	 * @param string $name  Hook suffix.
	 * @param mixed  $value
	 * @param mixed  ...$args
	 *
	 * @return mixed
	 */
	public static function apply_filters( string $name, $value, ...$args ) {
		$value = apply_filters( 'piecyfer/forms/' . $name, $value, ...$args );

		if ( ! self::pro_classes_loaded() ) {
			/** This filter is documented in the matching elementor-pro/modules/forms file. */
			$value = apply_filters( 'elementor_pro/forms/' . $name, $value, ...$args );
		}

		return $value;
	}
}
