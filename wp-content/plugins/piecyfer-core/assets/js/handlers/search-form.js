/**
 * Frontend handler for the `search-form` widget.
 *
 * Reproduces elementor-pro/assets/js/search-form.8941aba5c12cdb05fb7c.bundle.js
 * (../modules/theme-elements/assets/js/frontend/handlers/search-form.js)
 * verbatim, all three skin branches.
 *
 * WHAT THIS SITE ACTUALLY USES
 * ----------------------------
 * Every search-form instance in the captured baseline carries
 * `data-settings="{"skin":"classic"}"`, so only the `else` branch runs: a
 * `.elementor-search-form--focus` class added to the wrapper on input focus and
 * removed on blur. That is a focus RING, i.e. pure styling — the form itself is
 * a plain GET form that submits with no JS at all.
 *
 * So the honest summary for this widget is: on this site, losing the handler
 * costs a focus style and nothing else. The full-screen and minimal branches are
 * ported anyway because the `skin` control still exists in our SearchFormWidget,
 * and an editor who picks `full_screen` tomorrow would otherwise get a toggle
 * button that opens nothing — the same class of silent failure this whole layer
 * exists to prevent. Cost of carrying them: about forty lines.
 *
 * Note the full-screen branch is the one place Pro binds a document-level
 * listener (Esc to close). It is bound per widget instance, as Pro does.
 *
 * @package PieCyfer\Core
 */

( function ( window, $ ) {
	'use strict';

	if ( ! window.piecyferFrontend ) {
		return;
	}

	window.piecyferFrontend.register( 'search-form', function () {
		return elementorModules.frontend.handlers.Base.extend( {
			getDefaultSettings: function () {
				return {
					selectors: {
						wrapper: '.elementor-search-form',
						container: '.elementor-search-form__container',
						icon: '.elementor-search-form__icon',
						input: '.elementor-search-form__input',
						toggle: '.elementor-search-form__toggle',
						submit: '.elementor-search-form__submit',
						closeButton: '.dialog-close-button'
					},
					classes: {
						isFocus: 'elementor-search-form--focus',
						isFullScreen: 'elementor-search-form--full-screen',
						lightbox: 'elementor-lightbox'
					}
				};
			},

			getDefaultElements: function () {
				var selectors = this.getSettings( 'selectors' ),
					elements  = {};

				elements.$wrapper     = this.$element.find( selectors.wrapper );
				elements.$container   = this.$element.find( selectors.container );
				elements.$input       = this.$element.find( selectors.input );
				elements.$icon        = this.$element.find( selectors.icon );
				elements.$toggle      = this.$element.find( selectors.toggle );
				elements.$submit      = this.$element.find( selectors.submit );
				elements.$closeButton = this.$element.find( selectors.closeButton );

				return elements;
			},

			bindEvents: function () {
				var self         = this,
					$container   = self.elements.$container,
					$closeButton = self.elements.$closeButton,
					$input       = self.elements.$input,
					$wrapper     = self.elements.$wrapper,
					$icon        = self.elements.$icon,
					$toggle      = self.elements.$toggle,
					skin         = this.getElementSettings( 'skin' ),
					classes      = this.getSettings( 'classes' );

				// Every jQuery collection below is empty when the corresponding
				// markup is absent, and .on() on an empty collection is a no-op —
				// which is what makes this handler harmless on a skin whose
				// elements do not exist.

				var openFullScreenSearch = function () {
					$container.addClass( classes.isFullScreen ).addClass( classes.lightbox );
					$input.trigger( 'focus' );
				};

				var closeFullScreenSearch = function () {
					$container.removeClass( classes.isFullScreen ).removeClass( classes.lightbox );
					$toggle.trigger( 'focus' );
				};

				var triggerClickOnEnterSpace = function ( event ) {
					var ENTER_KEY = 13,
						SPACE_KEY = 32;

					if ( ENTER_KEY === event.keyCode || SPACE_KEY === event.keyCode ) {
						event.currentTarget.click();
						event.stopPropagation();
					}
				};

				if ( 'full_screen' === skin ) {
					// Activate on click or on Enter/Space keyup.
					$toggle
						.on( 'click', function () {
							openFullScreenSearch();
						} )
						.on( 'keyup', function ( event ) {
							triggerClickOnEnterSpace( event );
						} );

					// Deactivate when the click lands on the backdrop itself.
					$container.on( 'click', function ( event ) {
						if ( $container.hasClass( classes.isFullScreen ) && $container[ 0 ] === event.target ) {
							$container.removeClass( classes.isFullScreen ).removeClass( classes.lightbox );
						}
					} );

					$closeButton
						.on( 'click', function () {
							closeFullScreenSearch();
						} )
						.on( 'keyup', function ( event ) {
							triggerClickOnEnterSpace( event );
						} );

					// Esc closes. Pro routes this through a synthetic click on
					// the container so the backdrop branch above does the work.
					elementorFrontend.elements.$document.on( 'keyup', function ( event ) {
						var ESC_KEY = 27;

						if ( ESC_KEY === event.keyCode ) {
							if ( $container.hasClass( classes.isFullScreen ) ) {
								$container.trigger( 'click' );
							}
						}
					} );
				} else {
					// The only branch this site exercises.
					$input.on( {
						focus: function () {
							$wrapper.addClass( classes.isFocus );
						},
						blur: function () {
							$wrapper.removeClass( classes.isFocus );
						}
					} );
				}

				if ( 'minimal' === skin ) {
					$icon.on( 'click', function () {
						$wrapper.addClass( classes.isFocus );
						$input.trigger( 'focus' );
					} );
				}
			}
		} );
	} );
} )( window, jQuery );
