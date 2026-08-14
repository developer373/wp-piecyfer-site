/**
 * Frontend handler for the `nav-menu` widget.
 *
 * Reproduces elementor-pro/assets/js/nav-menu.1a66dd30011cc2fc8842.bundle.js
 * (../modules/nav-menu/assets/js/frontend/handlers/nav-menu.js) plus the
 * module-level SmartMenus patches from
 * ../modules/nav-menu/assets/js/frontend/frontend.js.
 *
 * WHAT THIS BUYS BACK
 * -------------------
 * On this site the Pro nav-menu widget is the TABLET AND MOBILE header menu
 * (the desktop menu is ElementsKit's, which is free and unaffected). With Pro
 * deactivated and no handler:
 *
 *   - the burger renders and does nothing: no `aria-expanded`, no
 *     `.elementor-active`, no `--menu-height`, so the dropdown stays at zero
 *     height for ever;
 *   - `<a class="has-submenu">` never exists, because SmartMenus is what adds
 *     it, so every parent item is an `href="#"` that goes nowhere and roughly
 *     three quarters of the site is unreachable from a phone.
 *
 * Both are invisible to a screenshot: the burger icon and the menu markup are
 * rendered server-side and look perfect.
 *
 * WHERE SMARTMENUS COMES FROM
 * ---------------------------
 * SmartMenus is a third-party MIT library. Elementor FREE does not ship it —
 * Elementor Pro does, and registers the `smartmenus` script handle in
 * elementor-pro/plugin.php. Our NavMenuWidget::get_script_depends() already
 * names that handle, so the day Pro is deactivated the handle stops existing
 * and `wp_enqueue_script('smartmenus')` becomes a silent no-op.
 *
 * We therefore vendor the library at assets/lib/smartmenus/ (v1.2.1, MIT,
 * byte-identical to Pro's copy) and src/Frontend.php registers the same
 * `smartmenus` handle — but only if nothing else has claimed it, so while Pro
 * is still installed Pro's copy keeps winning and nothing changes.
 *
 * @package PieCyfer\Core
 */

( function ( window, $ ) {
	'use strict';

	if ( ! window.piecyferFrontend ) {
		return;
	}

	window.piecyferFrontend.register( 'nav-menu', function () {
		/*
		 * Pro applies these two patches once, from its module constructor,
		 * before any handler runs. They are global to the SmartMenus library
		 * rather than per-widget, so they are applied here on first build.
		 *
		 *   isCSSOn(): SmartMenus decides "is my CSS loaded?" by checking
		 *   whether the first link is `display:inline`. Elementor's stylesheet
		 *   makes that check unreliable, so Pro forces it true. Without this
		 *   override SmartMenus refuses to handle any event at all and the
		 *   sub-menus never open — which is the single easiest way to get this
		 *   port subtly wrong.
		 */
		if ( $.fn.smartmenus ) {
			$.SmartMenus.prototype.isCSSOn = function () {
				return true;
			};

			if ( elementorFrontend.config.is_rtl ) {
				$.fn.smartmenus.defaults.rightToLeftSubMenus = true;
			}
		}

		return elementorModules.frontend.handlers.Base.extend( {
			stretchElement: null,

			getDefaultSettings: function () {
				return {
					selectors: {
						menu: '.elementor-nav-menu',
						anchorLink: '.elementor-nav-menu--main .elementor-item-anchor',
						dropdownMenu: '.elementor-nav-menu__container.elementor-nav-menu--dropdown',
						menuToggle: '.elementor-menu-toggle'
					},
					classes: {
						anchorItem: 'elementor-item-anchor',
						activeAnchorItem: 'elementor-item-active'
					}
				};
			},

			getDefaultElements: function () {
				var selectors = this.getSettings( 'selectors' ),
					elements  = {};

				elements.$menu                   = this.$element.find( selectors.menu );
				elements.$anchorLink             = this.$element.find( selectors.anchorLink );
				elements.$dropdownMenu           = this.$element.find( selectors.dropdownMenu );
				elements.$dropdownMenuFinalItems = elements.$dropdownMenu.find( '.menu-item:not(.menu-item-has-children) > a' );
				elements.$menuToggle             = this.$element.find( selectors.menuToggle );
				elements.$links                  = elements.$dropdownMenu.find( 'a.elementor-item' );

				return elements;
			},

			dropdownMenuHeightControllerConfig: function () {
				var selectors = this.getSettings( 'selectors' );

				return {
					elements: {
						$element: this.$element,
						$dropdownMenuContainer: this.$element.find( selectors.dropdownMenu ),
						$menuToggle: this.$element.find( selectors.menuToggle )
					},
					attributes: {
						menuToggleState: 'aria-expanded'
					},
					settings: {
						// Fixed at 1000vmax so the CSS close transition has a
						// real height to animate from. Pro's value, verbatim.
						dropdownMenuContainerMaxHeight: '1000vmax',
						menuHeightCssVarName: '--menu-height'
					}
				};
			},

			bindEvents: function () {
				if ( ! this.elements.$menu.length ) {
					return;
				}

				this.elements.$menuToggle
					.on( 'click', this.toggleMenu.bind( this ) )
					.on( 'keyup', this.triggerClickOnEnterSpace.bind( this ) );

				if ( this.getElementSettings( 'full_width' ) ) {
					this.elements.$dropdownMenuFinalItems
						.on( 'click', this.toggleMenu.bind( this, false ) )
						.on( 'keyup', this.triggerClickOnEnterSpace.bind( this ) );
				}

				/*
				 * DIVERGENCE FROM PRO — deliberate, and the only one in this file.
				 *
				 * Pro passes `this.stretchMenu` here, unbound. `addListenerOnce`
				 * on the front end reduces to `$window.on('resize', callback)`,
				 * so jQuery invokes it with `this === window` and the first line
				 * — `this.getElementSettings(...)` — throws a TypeError on every
				 * single window resize. Elementor's handler base does not
				 * auto-bind methods (Module.extend only copies the prototype),
				 * so this is a genuine latent bug in Pro 3.25.0, not something
				 * the framework fixes up.
				 *
				 * Reproducing the bug would mean shipping a handler that throws
				 * inside a window resize listener, aborting every later resize
				 * handler on the page. We bind instead. Observable difference on
				 * this site: none — `full_width` is not in any nav-menu's
				 * data-settings, so stretchMenu takes the `reset()` branch,
				 * which is a no-op on an element that was never stretched.
				 */
				elementorFrontend.addListenerOnce(
					this.$element.data( 'model-cid' ),
					'resize',
					this.stretchMenu.bind( this )
				);

				elementorFrontend.addListenerOnce(
					this.$element.data( 'model-cid' ),
					'scroll',
					elementorFrontend.debounce(
						this.menuHeightController.reassignMobileMenuHeight.bind( this.menuHeightController ),
						250
					)
				);
			},

			initStretchElement: function () {
				this.stretchElement = new elementorModules.frontend.tools.StretchElement( {
					element: this.elements.$dropdownMenu
				} );
			},

			toggleNavLinksTabIndex: function ( enabled ) {
				if ( undefined === enabled ) {
					enabled = true;
				}

				this.elements.$links.attr( 'tabindex', enabled ? 0 : -1 );
			},

			/**
			 * The behaviour the harness asserts: aria-expanded, .elementor-active,
			 * aria-hidden and --menu-height all flip together, both ways.
			 *
			 * @param {boolean} [show] Force a state; omitted means toggle.
			 */
			toggleMenu: function ( show ) {
				var isDropdownVisible = this.elements.$menuToggle.hasClass( 'elementor-active' );

				if ( 'boolean' !== typeof show ) {
					show = ! isDropdownVisible;
				}

				this.elements.$menuToggle.attr( 'aria-expanded', show );
				this.elements.$dropdownMenu.attr( 'aria-hidden', ! show );
				this.elements.$menuToggle.toggleClass( 'elementor-active', show );

				this.toggleNavLinksTabIndex( show );

				this.menuHeightController.reassignMobileMenuHeight( this );

				if ( show && this.getElementSettings( 'full_width' ) ) {
					this.stretchElement.stretch();
				}
			},

			triggerClickOnEnterSpace: function ( event ) {
				var ENTER_KEY = 13,
					SPACE_KEY = 32;

				if ( ENTER_KEY === event.keyCode || SPACE_KEY === event.keyCode ) {
					event.currentTarget.click();
					event.stopPropagation();
				}
			},

			stretchMenu: function () {
				if ( this.getElementSettings( 'full_width' ) ) {
					this.stretchElement.stretch();
					this.elements.$dropdownMenu.css( 'top', this.elements.$menuToggle.outerHeight() );
				} else {
					this.stretchElement.reset();
				}
			},

			onInit: function () {
				this.menuHeightController = new window.piecyferFrontend.utils.DropdownMenuHeightController(
					this.dropdownMenuHeightControllerConfig()
				);

				elementorModules.frontend.handlers.Base.prototype.onInit.apply( this, arguments );

				if ( ! this.elements.$menu.length ) {
					return;
				}

				var elementSettings = this.getElementSettings(),
					/*
					 * Guard added: Pro reads `.submenu_icon.value` unconditionally.
					 * Every nav-menu on this site carries submenu_icon in its
					 * data-settings so the guard never changes behaviour here,
					 * but a widget saved without it would throw and take out
					 * every handler queued behind this one.
					 */
					iconValue = ( elementSettings.submenu_icon || {} ).value,
					subIndicatorsContent = '';

				if ( iconValue ) {
					// In the editor this is a class name; on the front end
					// Elementor has already rendered it to inline SVG markup.
					subIndicatorsContent = iconValue.indexOf( '<' ) > -1 ? iconValue : '<i class="' + iconValue + '"></i>';
				}

				/*
				 * SmartMenus is what creates `a.has-submenu`, the aria-expanded /
				 * aria-controls wiring and the slide up/down on the sub-menus.
				 * If the library did not load we still bind the burger above —
				 * a menu that opens without working sub-menus is far better than
				 * no menu at all — but we say so, because a missing dependency
				 * here is otherwise completely silent.
				 *
				 * subIndicators param — kept for backwards compatibility: when
				 * the old `indicator` control was 'none' the <span class="sub-arrow">
				 * wrapper is dropped entirely.
				 */
				if ( $.fn.smartmenus ) {
					this.elements.$menu.smartmenus( {
						subIndicators: '' !== subIndicatorsContent,
						subIndicatorsText: subIndicatorsContent,
						subIndicatorsPos: 'append',
						subMenusMaxWidth: '1000px'
					} );
				} else {
					window.piecyferFrontend.warn(
						'the "smartmenus" script is not on the page — nav-menu sub-menus will not expand'
					);
				}

				this.initStretchElement();
				this.stretchMenu();

				if ( ! elementorFrontend.isEditMode() ) {
					var classes = this.getSettings( 'classes' );

					this.anchorLinks = new window.piecyferFrontend.utils.AnchorLinks( this.elements.$anchorLink, classes );
					this.anchorLinks.initialize();
				}
			},

			onElementChange: function ( propertyName ) {
				if ( 'full_width' === propertyName ) {
					this.stretchMenu();
				}
			}
		} );
	} );
} )( window, jQuery );
