/**
 * PieCyfer Core — frontend handler bootstrap.
 *
 * This file is the equivalent of Elementor Pro's `elements-handlers.js`: it owns
 * the registry of widget handlers and the moment they are attached to
 * Elementor's `frontend/element_ready/*` hooks. It deliberately does NOT
 * implement a module system of its own — every handler in `handlers/` extends
 * `elementorModules.frontend.handlers.Base` (or `SwiperBase`) from Elementor
 * FREE, which is what keeps `data-settings`, responsive breakpoints, editor
 * previews and `onElementChange()` working exactly as they do today.
 *
 * Load order (enforced by wp_register_script dependencies in src/Frontend.php):
 *
 *     jquery
 *       └─ elementor-frontend        (defines elementorFrontend + elementorModules)
 *            └─ piecyfer-frontend    (this file — defines window.piecyferFrontend)
 *                 └─ piecyfer-handler-*  (each calls piecyferFrontend.register())
 *
 * Handlers register themselves at parse time but are only *built* on
 * `elementor/frontend/init`, because the base classes they extend do not exist
 * until Elementor's frontend bundle has run.
 *
 * ---------------------------------------------------------------------------
 * WHY THE try/catch WRAPPER EXISTS
 * ---------------------------------------------------------------------------
 * Elementor dispatches `frontend/element_ready/<widget>` through
 * `hooks.doAction()`, which runs every registered callback in a plain loop with
 * no error isolation. One handler that throws therefore silently prevents every
 * handler registered after it from ever running — including third-party ones
 * (VamTam's `vamtam-nav-menu`, ElementsKit's) that have nothing to do with us.
 *
 * Pro attaches handlers via `elementorFrontend.elementsHandler.attachHandler()`,
 * which has no such guard. We use the same public API one level down —
 * `hooks.addAction()` + `elementsHandler.addHandler()`, which is literally what
 * `attachHandler` expands to — and wrap the construction. Same mechanism, same
 * ordering, one extra safety net.
 *
 * @package PieCyfer\Core
 */

( function ( window, $ ) {
	'use strict';

	if ( window.piecyferFrontend ) {
		return;
	}

	var registry = [];
	var api      = {};

	/**
	 * Non-fatal diagnostics. Never throws, never depends on console existing.
	 *
	 * @param {string} message Human-readable context.
	 * @param {*}      error   Optional caught error.
	 */
	function warn( message, error ) {
		try {
			if ( window.console && window.console.warn ) {
				window.console.warn( '[piecyfer-core] ' + message, error || '' );
			}
		} catch ( e ) {
			// A logging failure must never become a page failure.
		}
	}

	api.warn = warn;

	/**
	 * Register a handler for a widget.
	 *
	 * @param {string}   elementName Widget name as it appears in `data-widget_type`
	 *                               before the dot — e.g. `nav-menu`.
	 * @param {Function} factory     Returns the handler class. Called once, on
	 *                               `elementor/frontend/init`, so it may safely
	 *                               reference `elementorModules`.
	 * @param {string}   [skin]      Skin segment after the dot. Defaults to
	 *                               `default`, which is the only skin every
	 *                               widget we ship uses on this site.
	 */
	api.register = function ( elementName, factory, skin ) {
		if ( ! elementName || 'function' !== typeof factory ) {
			warn( 'ignored a malformed handler registration for "' + elementName + '"' );
			return;
		}

		registry.push( {
			elementName: elementName,
			factory: factory,
			skin: skin || 'default'
		} );
	};

	/**
	 * Utilities shared by more than one handler.
	 *
	 * Pro exposes the same objects on the `elementorProFrontend` global. We do
	 * not reuse that global: it only exists while Pro is active, and depending
	 * on it would reintroduce exactly the dependency this plugin removes.
	 */
	api.utils = {};

	/**
	 * Verbatim port of Pro's `DropdownMenuHeightController`.
	 *
	 * Source: elementor-pro/assets/js/frontend.js
	 *         (../assets/dev/js/frontend/utils/dropdown-menu-height-controller.js)
	 *
	 * Sets the `--menu-height` custom property on the mobile dropdown container.
	 * When the widget sits inside a sticky element the dropdown must stop at the
	 * bottom of the viewport rather than run off the page; otherwise it gets the
	 * fixed `1000vmax`, which is what makes the CSS open/close transition
	 * animate to a real height instead of snapping.
	 *
	 * Written as a constructor function rather than a `class` so this file stays
	 * parseable as plain ES5 — it is the only file that must survive being
	 * loaded before anything else of ours.
	 *
	 * @param {Object} widgetConfig See NavMenu#dropdownMenuHeightControllerConfig.
	 * @constructor
	 */
	function DropdownMenuHeightController( widgetConfig ) {
		this.widgetConfig = widgetConfig;
	}

	DropdownMenuHeightController.prototype.calculateStickyMenuNavHeight = function () {
		this.widgetConfig.elements.$dropdownMenuContainer.css( this.widgetConfig.settings.menuHeightCssVarName, '' );

		var menuToggleHeight = this.widgetConfig.elements.$dropdownMenuContainer.offset().top - $( window ).scrollTop();

		return elementorFrontend.elements.$window.height() - menuToggleHeight;
	};

	DropdownMenuHeightController.prototype.isElementSticky = function () {
		return this.widgetConfig.elements.$element.hasClass( 'elementor-sticky' ) ||
			this.widgetConfig.elements.$element.parents( '.elementor-sticky' ).length;
	};

	DropdownMenuHeightController.prototype.getMenuHeight = function () {
		return this.isElementSticky() ?
			this.calculateStickyMenuNavHeight() + 'px' :
			this.widgetConfig.settings.dropdownMenuContainerMaxHeight;
	};

	DropdownMenuHeightController.prototype.setMenuHeight = function ( menuHeight ) {
		this.widgetConfig.elements.$dropdownMenuContainer.css( this.widgetConfig.settings.menuHeightCssVarName, menuHeight );
	};

	DropdownMenuHeightController.prototype.reassignMobileMenuHeight = function () {
		var menuHeight = this.isToggleActive() ? this.getMenuHeight() : 0;

		return this.setMenuHeight( menuHeight );
	};

	DropdownMenuHeightController.prototype.isToggleActive = function () {
		var $menuToggle = this.widgetConfig.elements.$menuToggle;
		var attributes  = this.widgetConfig.attributes || {};

		// New approach: aria attributes instead of css classes.
		if ( attributes.menuToggleState ) {
			return 'true' === $menuToggle.attr( attributes.menuToggleState );
		}

		return $menuToggle.hasClass( ( this.widgetConfig.classes || {} ).menuToggleActiveClass );
	};

	api.utils.DropdownMenuHeightController = DropdownMenuHeightController;

	/**
	 * Verbatim port of Pro's `AnchorLinks`.
	 *
	 * Source: elementor-pro/assets/js/nav-menu.*.bundle.js
	 *         (../assets/dev/js/frontend/utils/anchor-link.js)
	 *
	 * Watches every same-page `#anchor` link in the MAIN menu with an
	 * IntersectionObserver and toggles `elementor-item-active` +
	 * `aria-current="location"` as the target scrolls through the middle of the
	 * viewport. Pro skips this entirely in the editor.
	 *
	 * @param {jQuery} $anchorLinks Anchor links inside the main menu.
	 * @param {Object} classes      { anchorItem, activeAnchorItem }
	 * @constructor
	 */
	function AnchorLinks( $anchorLinks, classes ) {
		this.observer          = null;
		this.$anchorLinks      = $anchorLinks;
		this.activeAnchorClass = classes.activeAnchorItem;
		this.anchorClass       = classes.anchorItem;
	}

	AnchorLinks.prototype.getViewportHeight = function () {
		return window.innerHeight;
	};

	AnchorLinks.prototype.bindEvents = function () {
		this.onResize = this.onResize.bind( this );
		window.addEventListener( 'resize', this.onResize );
	};

	AnchorLinks.prototype.initialize = function () {
		// IntersectionObserver is the only hard requirement; without it the
		// menu simply never highlights, which is what a no-JS page does anyway.
		if ( ! window.IntersectionObserver || ! this.$anchorLinks || ! this.$anchorLinks.length ) {
			return;
		}

		this.viewPortHeight = this.getViewportHeight();
		this.followMenuAnchors();
		this.bindEvents();
	};

	AnchorLinks.prototype.followMenuAnchors = function () {
		var self = this;

		this.$anchorLinks.each( function ( index, anchorLink ) {
			if ( location.pathname === anchorLink.pathname && '' !== anchorLink.hash ) {
				self.followMenuAnchor( $( anchorLink ) );
			}
		} );
	};

	AnchorLinks.prototype.followMenuAnchor = function ( $element ) {
		var $targetElement = $element.hasClass( this.anchorClass ) ? $element : $element.closest( '.' + this.anchorClass );
		var anchorElement  = this.getAnchorElement( $element );

		if ( ! anchorElement ) {
			return;
		}

		var options = this.getObserverOptions( anchorElement );

		this.observer = this.createObserver( $targetElement, $element, options );
		this.observer.observe( anchorElement );
	};

	AnchorLinks.prototype.getAnchorElement = function ( $element ) {
		var anchorSelector = $element[ 0 ].hash;

		try {
			// `decodeURIComponent` for UTF8 characters in the hash.
			return document.querySelector( decodeURIComponent( anchorSelector ) );
		} catch ( e ) {
			return null;
		}
	};

	AnchorLinks.prototype.getObserverOptions = function ( element ) {
		return {
			root: null,
			rootMargin: this.calculateRootMargin( element )
		};
	};

	AnchorLinks.prototype.calculateRootMargin = function ( element ) {
		var anchorHeight = ( element && element.offsetHeight ) || 0;
		var isAnchorHeightLargerThanHalfViewport = anchorHeight > this.viewPortHeight / 2;
		var rootMarginBlockEnd   = -1 * this.viewPortHeight / 2;
		var rootMarginBlockStart = isAnchorHeightLargerThanHalfViewport ? rootMarginBlockEnd : 0;

		return rootMarginBlockStart + 'px 0px ' + rootMarginBlockEnd + 'px 0px';
	};

	AnchorLinks.prototype.createObserver = function ( $targetElement, $element, options ) {
		var self = this;

		return new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				$targetElement.toggleClass( self.activeAnchorClass, entry.isIntersecting );
				$element.attr( 'aria-current', entry.isIntersecting ? 'location' : '' );
			} );
		}, options );
	};

	AnchorLinks.prototype.onResize = function () {
		this.viewPortHeight = this.getViewportHeight();

		if ( this.observer ) {
			this.observer.disconnect();
		}

		this.followMenuAnchors();
	};

	api.utils.AnchorLinks = AnchorLinks;

	/**
	 * Build one registered handler and bind it to its element_ready hook.
	 *
	 * @param {Object} entry Registry entry.
	 */
	function attach( entry ) {
		var HandlerClass;
		var elementName = entry.elementName + '.' + entry.skin;

		try {
			HandlerClass = entry.factory();
		} catch ( e ) {
			warn( 'could not build the handler class for "' + elementName + '"', e );
			return;
		}

		if ( ! HandlerClass ) {
			// A factory may legitimately decline — e.g. the gallery handler when
			// Elementor free's e-gallery library is not on the page.
			return;
		}

		elementorFrontend.hooks.addAction( 'frontend/element_ready/' + elementName, function ( $element ) {
			try {
				// No-op when the element is absent or empty. Elementor only fires
				// this hook with a real scope, but a defensive check costs nothing
				// and a throw here would take out every later handler.
				if ( ! $element || ! $element.length ) {
					return;
				}

				elementorFrontend.elementsHandler.addHandler( HandlerClass, {
					$element: $element,
					elementName: elementName
				} );
			} catch ( e ) {
				warn( 'handler "' + elementName + '" threw while initialising', e );
			}
		} );
	}

	/**
	 * Attach everything. Safe to call more than once only in the sense that it
	 * will not throw — it is guarded by `initialised` so hooks are never doubled.
	 */
	var initialised = false;

	api.init = function () {
		if ( initialised ) {
			return;
		}

		if ( ! window.elementorFrontend || ! elementorFrontend.hooks || ! elementorFrontend.elementsHandler ) {
			warn( 'elementorFrontend is not available — no handlers attached' );
			return;
		}

		if ( ! window.elementorModules || ! elementorModules.frontend || ! elementorModules.frontend.handlers ) {
			warn( 'elementorModules.frontend.handlers is not available — no handlers attached' );
			return;
		}

		initialised = true;

		for ( var i = 0; i < registry.length; i++ ) {
			attach( registry[ i ] );
		}
	};

	window.piecyferFrontend = api;

	/*
	 * Same signal Elementor's own bundles and VamTam's nav-menu script use.
	 * Our scripts are footer-enqueued with `elementor-frontend` as a dependency,
	 * so they always parse before Elementor fires this on document ready.
	 */
	$( window ).on( 'elementor/frontend/init', function () {
		try {
			api.init();
		} catch ( e ) {
			warn( 'bootstrap failed', e );
		}
	} );
} )( window, jQuery );
