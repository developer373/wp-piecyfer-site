/*
 * PieCyfer popup frontend.
 *
 * Replaces the popup half of elementor-pro/assets/js/elements-handlers.js —
 * specifically `modules/popup/assets/js/frontend/{frontend,document,triggers,
 * timing}.js` plus `assets/dev/js/frontend/utils/modal-keyboard-handler.js` and
 * the close-icon half of `utils/icons/*.js`.
 *
 * Everything it builds on is Elementor **free**:
 *   elementorModules.frontend.Document      frontend-modules.js
 *   elementorFrontend.getDialogsManager()   frontend.js  (lib/dialog)
 *   elementorFrontend.utils.urlActions      frontend.js
 *   elementorFrontend.storage               core/common/assets/js/utils/storage.js
 *
 * ---------------------------------------------------------------------------
 * TWO EXTERNAL CONTRACTS THIS FILE MUST HONOUR
 * ---------------------------------------------------------------------------
 *
 * 1. The theme's own JavaScript drives these objects directly.
 *    `vamtam-elementor-frontend.js` (`VamtamPopupsHandler`, `VamtamActionLinksHandler`)
 *    walks `elementorFrontend.documentsManager.documents`, calls `initModal()`,
 *    `getModal()`, `getDocumentSettings()` and `showModal()` on each popup, reads
 *    `instance.$element`, and listens for `elementor/popup/show` and
 *    `elementor/popup/hide` on `window` expecting `event.detail.{id,instance}`.
 *    That file is VamTam's, not Pro's, so it is still there after the cutover.
 *    Renaming any of those is a silent breakage of the theme's popup features
 *    (retain-position, align-with-selector, open-on-hover, focus handling).
 *
 * 2. The `#elementor-action:action=popup:open` links are the only way any popup
 *    on this site opens. Popup 7718 has no triggers and no timing; the six
 *    buttons carrying the `popup` dynamic tag are the entire trigger surface.
 *    `urlActions.addAction( 'popup:open', … )` on `components:init` is therefore
 *    the load-bearing line in this file.
 */

( function ( $ ) {
	'use strict';

	var config = window.PieCyferPopupConfig || { hasPopups: false };

	/**
	 * The close-icon SVG, for the `e_font_icon_svg` experiment.
	 *
	 * That experiment is ACTIVE on this site, so this is the branch that runs:
	 * the dialog's close button is an <svg><use> rather than an <i class="eicon-close">.
	 * The Document's `close_button_color` control writes both `color` on `i` and
	 * `fill` on `svg` for exactly this reason.
	 *
	 * Port of elementor-pro `utils/icons/manager.js` + `utils/icons/e-icons.js`,
	 * reduced to the one icon a popup needs.
	 */
	var closeIcon = ( function () {
		var PREFIX = 'eicon-',
			NAME = 'close',
			PATH = 'M742 167L500 408 258 167C246 154 233 150 217 150 196 150 179 158 167 167 154 179 150 196 150 212 150 229 154 242 171 254L408 500 167 742C138 771 138 800 167 829 196 858 225 858 254 829L496 587 738 829C750 842 767 846 783 846 800 846 817 842 829 829 842 817 846 804 846 783 846 767 842 750 829 737L588 500 833 258C863 229 863 200 833 171 804 137 775 137 742 167Z',
			SVG_NS = 'http://www.w3.org/2000/svg',
			symbolsContainer = null;

		function getSymbolsContainer() {
			if ( symbolsContainer ) {
				return symbolsContainer;
			}

			// Elementor may already have created this container for the icons it
			// renders itself; reuse it rather than adding a second one.
			symbolsContainer = document.getElementById( 'e-font-icon-svg-symbols' );

			if ( ! symbolsContainer ) {
				symbolsContainer = document.createElementNS( SVG_NS, 'svg' );
				symbolsContainer.setAttributeNS( null, 'style', 'display: none;' );
				symbolsContainer.setAttributeNS( null, 'class', 'e-font-icon-svg-symbols' );
				document.body.appendChild( symbolsContainer );
			}

			return symbolsContainer;
		}

		return {
			get element() {
				var container = getSymbolsContainer(),
					elementName = PREFIX + NAME,
					elementSelector = '#' + elementName,
					symbol,
					svg;

				if ( ! container.querySelector( elementSelector ) ) {
					symbol = document.createElementNS( SVG_NS, 'symbol' );
					symbol.id = elementName;
					symbol.innerHTML = '<path d="' + PATH + '"></path>';
					symbol.setAttributeNS( null, 'viewBox', '0 0 1000 1000' );
					container.appendChild( symbol );
				}

				svg = document.createElementNS( SVG_NS, 'svg' );
				svg.innerHTML = '<use xlink:href="' + elementSelector + '" />';
				svg.setAttributeNS( null, 'class', 'e-font-icon-svg e-' + elementName );

				return svg;
			},
		};
	}() );

	/**
	 * Keyboard focus trap for an open popup.
	 *
	 * Port of elementor-pro `utils/modal-keyboard-handler.js`. Reached whenever
	 * `a11y_navigation` is on — and it is on for popup 7718, by default rather
	 * than by a saved value.
	 */
	function ModalKeyboardHandler( elementConfig ) {
		this.config = elementConfig;
		this.lastFocusableElement = null;
		this.firstFocusableElement = null;
		this.modalTriggerElement = null;

		this.onKeyDownPressed = this.onKeyDownPressed.bind( this );
		this.onCloseModal = this.onCloseModal.bind( this );
	}

	ModalKeyboardHandler.prototype.onOpenModal = function () {
		this.initializeElements();
		this.setTriggerElement();
		this.changeFocus();
		this.bindEvents();
	};

	ModalKeyboardHandler.prototype.onCloseModal = function () {
		elementorFrontend.elements.$window.off( 'keydown', this.onKeyDownPressed );

		// Send focus back where it came from, or a keyboard user is stranded at
		// the top of the document after closing.
		if ( this.modalTriggerElement ) {
			this.setFocusToElement( this.modalTriggerElement );
		}
	};

	ModalKeyboardHandler.prototype.bindEvents = function () {
		elementorFrontend.elements.$window.on( 'keydown', this.onKeyDownPressed );
		elementorFrontend.elements.$window.on( 'elementor/popup/hide', this.onCloseModal );
	};

	ModalKeyboardHandler.prototype.getFocusableElements = function () {
		// `:focusable` is jQuery UI's selector, which Elementor ships. Pro uses it
		// for popups specifically and a hand-rolled list elsewhere.
		return this.config.$modalElements.find( ':focusable' );
	};

	ModalKeyboardHandler.prototype.initializeElements = function () {
		var $focusable = this.getFocusableElements();

		if ( ! $focusable.length ) {
			return;
		}

		this.firstFocusableElement = $focusable[ 0 ];
		this.lastFocusableElement = $focusable[ $focusable.length - 1 ];
	};

	ModalKeyboardHandler.prototype.setTriggerElement = function () {
		this.modalTriggerElement = elementorFrontend.elements.window.document.activeElement || null;
	};

	ModalKeyboardHandler.prototype.changeFocus = function () {
		if ( this.firstFocusableElement ) {
			this.setFocusToElement( this.firstFocusableElement );
			return;
		}

		// A popup with nothing focusable in it still has to receive focus, or the
		// tab order simply continues behind the overlay.
		this.config.$elementWrapper.attr( 'tabindex', '0' );
		this.setFocusToElement( this.config.$elementWrapper[ 0 ] );
	};

	ModalKeyboardHandler.prototype.onKeyDownPressed = function ( keyDownEvent ) {
		var TAB_KEY = 9,
			isShiftPressed = keyDownEvent.shiftKey,
			isTabPressed = 'Tab' === keyDownEvent.key || TAB_KEY === keyDownEvent.keyCode,
			isContentWrapperFocused = '0' === this.config.$elementWrapper.attr( 'tabindex' );

		if ( isTabPressed && isContentWrapperFocused ) {
			keyDownEvent.preventDefault();
		} else if ( isTabPressed ) {
			this.onTabKeyPressed( isShiftPressed, keyDownEvent );
		}
	};

	ModalKeyboardHandler.prototype.onTabKeyPressed = function ( isShiftPressed, keyDownEvent ) {
		var activeElement = elementorFrontend.elements.window.document.activeElement;

		if ( elementorFrontend.isEditMode() ) {
			this.initializeElements();
		}

		if ( isShiftPressed ) {
			if ( activeElement === this.firstFocusableElement ) {
				this.setFocusToElement( this.lastFocusableElement );
				keyDownEvent.preventDefault();
			}

			return;
		}

		if ( activeElement === this.lastFocusableElement ) {
			this.setFocusToElement( this.firstFocusableElement );
			keyDownEvent.preventDefault();
		}
	};

	ModalKeyboardHandler.prototype.setFocusToElement = function ( element ) {
		// Deferred so focus lands after the entrance animation, not during it.
		setTimeout( function () {
			if ( element && element.focus ) {
				element.focus();
			}
		}, 100 );
	};

	/*
	 * ---------------------------------------------------------------------
	 * Triggers — what opens a popup by itself.
	 *
	 * Unused on this site (popup 7718 has an empty `triggers` array, and the key
	 * is not even emitted into data-elementor-settings because the popup is not
	 * matched by display conditions). Ported anyway: the settings are registered
	 * server-side, so the day one is switched on it has to work.
	 * ---------------------------------------------------------------------
	 */

	function TriggerBase( settings, callback ) {
		this.settings = settings || {};
		this.callback = callback;
	}

	TriggerBase.prototype.getTriggerSetting = function ( key ) {
		return this.settings[ this.getName() + '_' + key ];
	};

	TriggerBase.prototype.run = function () {};
	TriggerBase.prototype.destroy = function () {};

	function extendTrigger( name, proto ) {
		var Trigger = function () {
			TriggerBase.apply( this, arguments );

			if ( proto.init ) {
				proto.init.call( this );
			}
		};

		Trigger.prototype = Object.create( TriggerBase.prototype );
		Trigger.prototype.constructor = Trigger;
		Trigger.prototype.getName = function () {
			return name;
		};

		Object.keys( proto ).forEach( function ( key ) {
			if ( 'init' !== key ) {
				Trigger.prototype[ key ] = proto[ key ];
			}
		} );

		return Trigger;
	}

	var PageLoadTrigger = extendTrigger( 'page_load', {
		run: function () {
			this.timeout = setTimeout( this.callback, this.getTriggerSetting( 'delay' ) * 1000 );
		},
		destroy: function () {
			clearTimeout( this.timeout );
		},
	} );

	var ScrollingTrigger = extendTrigger( 'scrolling', {
		init: function () {
			this.checkScroll = this.checkScroll.bind( this );
			this.lastScrollOffset = 0;
		},
		checkScroll: function () {
			var scrollDirection = window.scrollY > this.lastScrollOffset ? 'down' : 'up',
				requestedDirection = this.getTriggerSetting( 'direction' ),
				fullScroll,
				scrollPercent;

			this.lastScrollOffset = window.scrollY;

			if ( scrollDirection !== requestedDirection ) {
				return;
			}

			// Any upward scroll counts; downward scrolling has a percentage gate.
			if ( 'up' === scrollDirection ) {
				this.callback();
				return;
			}

			fullScroll = elementorFrontend.elements.$document.height() - window.innerHeight;
			scrollPercent = ( window.scrollY / fullScroll ) * 100;

			if ( scrollPercent >= this.getTriggerSetting( 'offset' ) ) {
				this.callback();
			}
		},
		run: function () {
			elementorFrontend.elements.$window.on( 'scroll', this.checkScroll );
		},
		destroy: function () {
			elementorFrontend.elements.$window.off( 'scroll', this.checkScroll );
		},
	} );

	var ScrollingToTrigger = extendTrigger( 'scrolling_to', {
		run: function () {
			var $target;

			try {
				$target = $( this.getTriggerSetting( 'selector' ) );
			} catch ( e ) {
				// An invalid selector is a configuration mistake, not a reason to
				// throw on every page view.
				return;
			}

			if ( ! $target.length ) {
				return;
			}

			this.setUpIntersectionObserver();
			this.observer.observe( $target[ 0 ] );
		},
		setUpIntersectionObserver: function () {
			var self = this;

			this.observer = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						self.callback();
					}
				} );
			} );
		},
		destroy: function () {
			if ( this.observer ) {
				this.observer.disconnect();
			}
		},
	} );

	var ClickTrigger = extendTrigger( 'click', {
		init: function () {
			this.checkClick = this.checkClick.bind( this );
			this.clicksCount = 0;
		},
		checkClick: function () {
			this.clicksCount++;

			if ( this.clicksCount === this.getTriggerSetting( 'times' ) ) {
				this.callback();
			}
		},
		run: function () {
			elementorFrontend.elements.$body.on( 'click', this.checkClick );
		},
		destroy: function () {
			elementorFrontend.elements.$body.off( 'click', this.checkClick );
		},
	} );

	var InactivityTrigger = extendTrigger( 'inactivity', {
		init: function () {
			this.restartTimer = this.restartTimer.bind( this );
		},
		run: function () {
			this.startTimer();
			elementorFrontend.elements.$document.on( 'keypress mousemove', this.restartTimer );
		},
		startTimer: function () {
			this.timeOut = setTimeout( this.callback, this.getTriggerSetting( 'time' ) * 1000 );
		},
		clearTimer: function () {
			clearTimeout( this.timeOut );
		},
		restartTimer: function () {
			this.clearTimer();
			this.startTimer();
		},
		destroy: function () {
			this.clearTimer();
			elementorFrontend.elements.$document.off( 'keypress mousemove', this.restartTimer );
		},
	} );

	var ExitIntentTrigger = extendTrigger( 'exit_intent', {
		init: function () {
			this.detectExitIntent = this.detectExitIntent.bind( this );
		},
		detectExitIntent: function ( event ) {
			// Leaving through the top edge only — sideways and downwards exits
			// are usually the scrollbar or another window.
			if ( event.clientY <= 0 ) {
				this.callback();
			}
		},
		run: function () {
			elementorFrontend.elements.$window.on( 'mouseleave', this.detectExitIntent );
		},
		destroy: function () {
			elementorFrontend.elements.$window.off( 'mouseleave', this.detectExitIntent );
		},
	} );

	var TRIGGER_CLASSES = {
		page_load: PageLoadTrigger,
		scrolling: ScrollingTrigger,
		scrolling_to: ScrollingToTrigger,
		click: ClickTrigger,
		inactivity: InactivityTrigger,
		exit_intent: ExitIntentTrigger,
	};

	function Triggers( settings, popupDocument ) {
		var self = this;

		this.settings = settings || {};
		this.document = popupDocument;
		this.triggers = [];

		Object.keys( TRIGGER_CLASSES ).forEach( function ( key ) {
			var trigger;

			// The group switcher's control id *is* the group name — see
			// DisplaySettings\Base::end_settings_group().
			if ( ! self.settings[ key ] ) {
				return;
			}

			trigger = new TRIGGER_CLASSES[ key ]( self.settings, function () {
				self.onTriggerFired();
			} );

			trigger.run();
			self.triggers.push( trigger );
		} );
	}

	Triggers.prototype.onTriggerFired = function () {
		/*
		 * One argument, deliberately — and it lands in `event`, not in
		 * `avoidMultiple`.
		 *
		 * Pro does exactly this (`triggers.js` calls `showModal( true )` while
		 * `showModal` reads avoidMultiple from `arguments[1]`), with the result
		 * that the `avoid_multiple_popups` setting never actually suppresses
		 * anything in 3.25.4. Reproduced rather than fixed: this is a port, the
		 * setting is unused on this site, and "our popups behave differently from
		 * the ones we are replacing" is not a surprise worth introducing during a
		 * cutover. Fix it deliberately later if the setting is ever switched on.
		 */
		this.document.showModal( true );
		this.destroyTriggers();
	};

	Triggers.prototype.destroyTriggers = function () {
		this.triggers.forEach( function ( trigger ) {
			trigger.destroy();
		} );

		this.triggers = [];
	};

	/*
	 * ---------------------------------------------------------------------
	 * Timing — whether this visitor is allowed to see the popup at all.
	 *
	 * Also unused on this site. All state lives in the visitor's browser:
	 * localStorage under the `elementor` key for persisting counters, and
	 * sessionStorage for per-session ones. Nothing is recorded server-side, so
	 * none of this is enforceable and none of it is a security control.
	 * ---------------------------------------------------------------------
	 */

	function TimingBase( settings, popupDocument ) {
		this.settings = settings || {};
		this.document = popupDocument;
	}

	TimingBase.prototype.getTimingSetting = function ( key ) {
		return this.settings[ this.getName() + '_' + key ];
	};

	function extendTiming( name, proto ) {
		var Timing = function () {
			TimingBase.apply( this, arguments );

			if ( proto.init ) {
				proto.init.call( this );
			}
		};

		Timing.prototype = Object.create( TimingBase.prototype );
		Timing.prototype.constructor = Timing;
		Timing.prototype.getName = function () {
			return name;
		};

		Object.keys( proto ).forEach( function ( key ) {
			if ( 'init' !== key ) {
				Timing.prototype[ key ] = proto[ key ];
			}
		} );

		return Timing;
	}

	var PageViewsTiming = extendTiming( 'page_views', {
		check: function () {
			var pageViews = elementorFrontend.storage.get( 'pageViews' ),
				key = this.getName() + '_initialPageViews',
				initial = this.document.getStorage( key );

			// The count is relative to the first time this popup was eligible,
			// not to the visitor's first ever page view.
			if ( ! initial ) {
				this.document.setStorage( key, pageViews );
				initial = pageViews;
			}

			return pageViews - initial >= this.getTimingSetting( 'views' );
		},
	} );

	var SessionsTiming = extendTiming( 'sessions', {
		check: function () {
			var sessions = elementorFrontend.storage.get( 'sessions' ),
				key = this.getName() + '_initialSessions',
				initial = this.document.getStorage( key );

			if ( ! initial ) {
				this.document.setStorage( key, sessions );
				initial = sessions;
			}

			return sessions - initial >= this.getTimingSetting( 'sessions' );
		},
	} );

	var UrlTiming = extendTiming( 'url', {
		check: function () {
			var url = this.getTimingSetting( 'url' ),
				action = this.getTimingSetting( 'action' ),
				referrer = document.referrer,
				regexp;

			if ( 'regex' !== action ) {
				// XOR: "show" passes when the referrer matches, "hide" passes
				// when it does not.
				return ( 'hide' === action ) ^ ( -1 !== referrer.indexOf( url ) );
			}

			try {
				regexp = new RegExp( url );
			} catch ( e ) {
				return false;
			}

			return regexp.test( referrer );
		},
	} );

	var SourcesTiming = extendTiming( 'sources', {
		check: function () {
			var sources = this.getTimingSetting( 'sources' ),
				referrer,
				isInternal;

			// All three selected means no restriction at all.
			if ( 3 === sources.length ) {
				return true;
			}

			referrer = document.referrer.replace( /https?:\/\/(?:www\.)?/, '' );
			isInternal = 0 === referrer.indexOf( window.location.host.replace( 'www.', '' ) );

			if ( isInternal ) {
				return -1 !== sources.indexOf( 'internal' );
			}

			if ( -1 !== sources.indexOf( 'external' ) ) {
				return true;
			}

			if ( -1 !== sources.indexOf( 'search' ) ) {
				return /^(google|yahoo|bing|yandex|baidu)\./.test( referrer );
			}

			return false;
		},
	} );

	var LoggedInTiming = extendTiming( 'logged_in', {
		check: function () {
			var userConfig = elementorFrontend.config.user,
				rolesInHideList;

			// No user config means an anonymous visitor: nothing to hide from.
			if ( ! userConfig ) {
				return true;
			}

			if ( 'all' === this.getTimingSetting( 'users' ) ) {
				return false;
			}

			rolesInHideList = this.getTimingSetting( 'roles' ).filter( function ( role ) {
				return -1 !== userConfig.roles.indexOf( role );
			} );

			return ! rolesInHideList.length;
		},
	} );

	var DevicesTiming = extendTiming( 'devices', {
		check: function () {
			return -1 !== this.getTimingSetting( 'devices' ).indexOf( elementorFrontend.getCurrentDeviceMode() );
		},
	} );

	var BrowsersTiming = extendTiming( 'browsers', {
		check: function () {
			var targeted,
				flags;

			if ( 'all' === this.getTimingSetting( 'browsers' ) ) {
				return true;
			}

			targeted = this.getTimingSetting( 'browsers_options' );
			flags = elementorFrontend.utils.environment;

			return targeted.some( function ( browserName ) {
				return flags[ browserName ];
			} );
		},
	} );

	var ScheduleTiming = extendTiming( 'schedule', {
		init: function () {
			var startDate = this.getSettings( 'schedule_start_date' ),
				endDate = this.getSettings( 'schedule_end_date' ),
				serverDatetime = this.getSettings( 'schedule_server_datetime' );

			this.schedule = {
				timezone: this.getSettings( 'schedule_timezone' ),
				startDate: startDate ? new Date( startDate ) : false,
				endDate: endDate ? new Date( endDate ) : false,
				serverDatetime: serverDatetime ? new Date( serverDatetime ) : false,
			};
		},
		getSettings: function ( key ) {
			return this.settings[ key ];
		},
		getCurrentDateTime: function () {
			// "Site" timezone is resolved by comparing against the server clock
			// the PHP side stamped into a hidden control, because the browser has
			// no idea what the site's timezone is.
			if ( 'site' === this.schedule.timezone && this.schedule.serverDatetime ) {
				return new Date( this.schedule.serverDatetime );
			}

			return new Date();
		},
		check: function () {
			var now;

			if ( ! this.schedule.startDate && ! this.schedule.endDate ) {
				return true;
			}

			now = this.getCurrentDateTime();

			return ( ! this.schedule.startDate || now >= this.schedule.startDate ) &&
				( ! this.schedule.endDate || now <= this.schedule.endDate );
		},
	} );

	var TimesTiming = extendTiming( 'times', {
		init: function () {
			this.uniqueId = 'popup-' + this.document.getSettings( 'id' ) + '-impressions-count';

			this.times = {
				countOnOpen: this.settings.times_count,
				period: this.settings.times_period,
				showsLimit: parseInt( this.settings.times_times, 10 ),
			};

			// '' is the legacy "persisting" period, not "unset".
			if ( '' === this.times.period ) {
				this.times.period = false;
			}

			if ( '' === this.times.countOnOpen || 'close' === this.times.countOnOpen ) {
				this.times.countOnOpen = false;
				this.countOnHide();
			} else {
				this.times.countOnOpen = true;
			}
		},
		getTimeFrameInSeconds: function ( timeFrame ) {
			return {
				day: 86400,
				week: 604800,
				month: 2628288,
			}[ timeFrame ];
		},
		getImpressionsCount: function () {
			return parseInt( elementorFrontend.storage.get( this.uniqueId ) || 0, 10 );
		},
		incrementImpressionsCount: function () {
			var count;

			if ( ! this.times.period ) {
				elementorFrontend.storage.set( 'times', ( elementorFrontend.storage.get( 'times' ) || 0 ) + 1 );
				return;
			}

			if ( 'session' === this.times.period ) {
				window.sessionStorage.setItem(
					this.uniqueId,
					parseInt( window.sessionStorage.getItem( this.uniqueId ) || 0, 10 ) + 1
				);
				return;
			}

			count = this.getImpressionsCount();

			// The expiry is set once, on the first impression of the window, so
			// "3 per week" means three within a week of the first one.
			if ( ! elementorFrontend.storage.get( this.uniqueId ) ) {
				elementorFrontend.storage.set( this.uniqueId, count + 1, {
					lifetimeInSeconds: this.getTimeFrameInSeconds( this.times.period ),
				} );
				return;
			}

			elementorFrontend.storage.set( this.uniqueId, count + 1 );
		},
		countIfOnOpen: function () {
			if ( this.times.countOnOpen ) {
				this.incrementImpressionsCount();
			}
		},
		countOnHide: function () {
			var self = this;

			window.addEventListener( 'elementor/popup/hide', function () {
				self.incrementImpressionsCount();
			} );
		},
		check: function () {
			var impressionCount,
				showsLimit;

			if ( ! this.times.period ) {
				// Legacy path: the count lives on the document's own storage key.
				impressionCount = parseInt( this.document.getStorage( 'times' ) || 0, 10 );
				showsLimit = parseInt( this.getTimingSetting( 'times' ), 10 );

				this.countIfOnOpen();

				return impressionCount < showsLimit;
			}

			if ( 'session' === this.times.period ) {
				impressionCount = parseInt( window.sessionStorage.getItem( this.uniqueId ) || 0, 10 );
			} else {
				impressionCount = this.getImpressionsCount();
			}

			if ( impressionCount >= this.times.showsLimit ) {
				return false;
			}

			this.countIfOnOpen();

			return true;
		},
	} );

	/*
	 * Insertion order matters only for which check runs first; every one of them
	 * must pass. Kept in Pro's order so behaviour is identical when several are
	 * enabled and one of them writes storage as a side effect.
	 */
	var TIMING_CLASSES = {
		page_views: PageViewsTiming,
		sessions: SessionsTiming,
		url: UrlTiming,
		sources: SourcesTiming,
		logged_in: LoggedInTiming,
		devices: DevicesTiming,
		times: TimesTiming,
		browsers: BrowsersTiming,
		schedule: ScheduleTiming,
	};

	function checkTiming( settings, popupDocument ) {
		var passed = true;

		settings = settings || {};

		Object.keys( TIMING_CLASSES ).forEach( function ( key ) {
			var timing;

			if ( ! settings[ key ] ) {
				return;
			}

			timing = new TIMING_CLASSES[ key ]( settings, popupDocument );

			if ( ! timing.check() ) {
				passed = false;
			}
		} );

		return passed;
	}

	/* --------------------------------------------------------------------- */

	$( window ).on( 'elementor/frontend/init', function () {
		/**
		 * The popup document class.
		 *
		 * Port of elementor-pro `modules/popup/assets/js/frontend/document.js`.
		 */
		var PopupDocument = class extends elementorModules.frontend.Document {
			constructor() {
				super( ...arguments );

				this.keyboardHandler = null;
				this.closeButtonTimeout = null;
				this.currentAnimation = null;
			}

			bindEvents() {
				// "Open By Selector": a delegated click on an arbitrary selector.
				// Unset on popup 7718 — its links are action hashes instead.
				const openSelector = this.getDocumentSettings( 'open_selector' );

				if ( openSelector ) {
					elementorFrontend.elements.$body.on( 'click', openSelector, this.showModal.bind( this ) );
				}
			}

			/**
			 * Timing gates triggers, not the popup itself. A popup whose timing
			 * fails can still be opened by a link — which is the whole reason
			 * popup 7718 works with no triggers and no timing at all.
			 */
			startTiming() {
				if ( checkTiming( this.getDocumentSettings( 'timing' ), this ) ) {
					this.initTriggers();
				}
			}

			initTriggers() {
				this.triggers = new Triggers( this.getDocumentSettings( 'triggers' ), this );
			}

			showModal( event, avoidMultiple ) {
				const settings = this.getDocumentSettings();

				if ( ! this.isEdit ) {
					if ( ! elementorFrontend.isWPPreviewMode() ) {
						// Set by the `popup:close` action with "Don't Show Again".
						if ( this.getStorage( 'disable' ) ) {
							return;
						}

						if ( avoidMultiple && piecyferPopup.popupPopped && settings.avoid_multiple_popups ) {
							return;
						}
					}

					/*
					 * A fresh copy of the markup on every open.
					 *
					 * The previous instance carries the event handlers and any
					 * state the widgets inside it accumulated — a half-filled
					 * form, a validation message. Re-parsing `elementHTML` is what
					 * makes reopening a popup give you the popup as it was
					 * published rather than as you left it.
					 */
					this.$element = $( this.elementHTML );
					this.elements.$elements = this.$element.find( this.getSettings( 'selectors.elements' ) );
				}

				const modal = this.getModal(),
					$closeButton = modal.getElements( 'closeButton' );

				modal.setMessage( this.$element ).show();

				if ( ! this.isEdit ) {
					if ( settings.close_button_delay ) {
						$closeButton.hide();
						clearTimeout( this.closeButtonTimeout );
						this.closeButtonTimeout = setTimeout( function () {
							$closeButton.show();
						}, settings.close_button_delay * 1000 );
					}

					// The base class's implementation, not our no-op override —
					// this is where the widgets inside the popup come alive.
					super.runElementsHandlers();
				}

				this.setEntranceAnimation();

				// When counting on close, the timing module does the counting.
				if ( ! settings.timing || ! settings.timing.times_count ) {
					this.countTimes();
				}

				piecyferPopup.popupPopped = true;

				if ( ! this.isEdit && settings.a11y_navigation ) {
					this.handleKeyboardA11y();
				}
			}

			setEntranceAnimation() {
				const $widgetContent = this.getModal().getElements( 'widgetContent' ),
					settings = this.getDocumentSettings(),
					newAnimation = elementorFrontend.getCurrentDeviceSetting( settings, 'entrance_animation' );

				if ( this.currentAnimation ) {
					$widgetContent.removeClass( this.currentAnimation );
				}

				this.currentAnimation = newAnimation;

				if ( ! newAnimation ) {
					return;
				}

				const animationDuration = settings.entrance_animation_duration.size;

				$widgetContent.addClass( newAnimation );

				// Removed once it has played, or a later class change would
				// re-trigger it.
				setTimeout( function () {
					$widgetContent.removeClass( newAnimation );
				}, animationDuration * 1000 );
			}

			setExitAnimation() {
				const modal = this.getModal(),
					settings = this.getDocumentSettings(),
					$widgetContent = modal.getElements( 'widgetContent' ),
					newAnimation = elementorFrontend.getCurrentDeviceSetting( settings, 'exit_animation' ),
					animationDuration = newAnimation ? settings.entrance_animation_duration.size : 0,
					isEdit = this.isEdit,
					$element = this.$element;

				setTimeout( function () {
					if ( newAnimation ) {
						$widgetContent.removeClass( newAnimation + ' reverse' );
					}

					if ( ! isEdit ) {
						// Detached rather than hidden: the next open re-parses
						// elementHTML from scratch.
						$element.remove();
						modal.getElements( 'widget' ).hide();
					}
				}, animationDuration * 1000 );

				if ( newAnimation ) {
					$widgetContent.addClass( newAnimation + ' reverse' );
				}
			}

			handleKeyboardA11y() {
				if ( ! this.keyboardHandler ) {
					this.keyboardHandler = new ModalKeyboardHandler( this.getKeyboardHandlingConfig() );
				}

				this.keyboardHandler.onOpenModal();
			}

			getKeyboardHandlingConfig() {
				return {
					$modalElements: this.getModal().getElements( 'widgetContent' ),
					$elementWrapper: this.$element,
					modalType: 'popup',
					modalId: this.$element.data( 'elementor-id' ),
				};
			}

			/**
			 * Build the dialog lazily and memoise it on `getModal`.
			 *
			 * The theme's JavaScript depends on this exact shape: it tests for
			 * `doc.initModal`, and calls `doc.initModal()` when `doc.getModal` is
			 * missing. See the file header.
			 */
			initModal() {
				let modal;
				const self = this;

				this.getModal = function () {
					if ( modal ) {
						return modal;
					}

					const settings = self.getDocumentSettings(),
						id = self.getSettings( 'id' ),
						triggerPopupEvent = function ( eventType ) {
							const event = 'elementor/popup/' + eventType;

							/*
							 * Both a jQuery event and a native CustomEvent, and
							 * both are consumed: the theme's popup handlers listen
							 * on `window` for the CustomEvent and read
							 * `detail.{id,instance}`. Dropping either breaks
							 * VamTam's retain-position and align-with-selector
							 * features.
							 */
							elementorFrontend.elements.$document.trigger( event, [ id, self ] );

							window.dispatchEvent( new CustomEvent( event, {
								detail: {
									id: id,
									instance: self,
								},
							} ) );
						};

					let classes = 'elementor-popup-modal';

					if ( settings.classes ) {
						classes += ' ' + settings.classes;
					}

					const modalProperties = {
						// This id is what the document's CSS wrapper selector
						// targets — see Document::get_css_wrapper_selector().
						id: 'elementor-popup-modal-' + id,
						className: classes,
						closeButton: true,
						preventScroll: settings.prevent_scroll,
						onShow: function () {
							triggerPopupEvent( 'show' );
						},
						onHide: function () {
							triggerPopupEvent( 'hide' );
						},
						effects: {
							hide: function () {
								if ( settings.timing && settings.timing.times_count ) {
									self.countTimes();
								}

								self.setExitAnimation();
							},
							show: 'show',
						},
						hide: {
							auto: !! settings.close_automatically,
							autoDelay: settings.close_automatically * 1000,
							onBackgroundClick: ! settings.prevent_close_on_background_click,
							onOutsideClick: ! settings.prevent_close_on_background_click,
							onEscKeyPress: ! settings.prevent_close_on_esc_key,
							// A date picker renders outside the dialog, so an
							// outside-click close would fire on every date pick.
							ignore: '.flatpickr-calendar',
						},
						position: {
							enable: false,
						},
					};

					if ( elementorFrontend.config.experimentalFeatures.e_font_icon_svg ) {
						modalProperties.closeButtonOptions = {
							iconElement: closeIcon.element,
						};
					}

					// Set unconditionally, as Pro does: the font-icon class is
					// harmless when the SVG element is supplied as well.
					modalProperties.closeButtonClass = 'eicon-close';

					modal = elementorFrontend.getDialogsManager().createWidget( 'lightbox', modalProperties );

					modal.getElements( 'widgetContent' ).addClass( 'animated' );

					if ( self.isEdit ) {
						modal.getElements( 'closeButton' ).off( 'click' );
						modal.hide = function () {};
					}

					self.setCloseButtonPosition();

					return modal;
				};
			}

			setCloseButtonPosition() {
				const modal = this.getModal(),
					closeButtonPosition = this.getDocumentSettings( 'close_button_position' ),
					$closeButton = modal.getElements( 'closeButton' );

				// "Outside" moves it onto the widget, which sits outside the
				// content box; "inside" keeps it on the content itself.
				$closeButton.prependTo( modal.getElements( 'outside' === closeButtonPosition ? 'widget' : 'widgetContent' ) );
			}

			disable() {
				this.setStorage( 'disable', true );
			}

			setStorage( key, value, options ) {
				elementorFrontend.storage.set( 'popup_' + this.getSettings( 'id' ) + '_' + key, value, options );
			}

			getStorage( key, options ) {
				return elementorFrontend.storage.get( 'popup_' + this.getSettings( 'id' ) + '_' + key, options );
			}

			countTimes() {
				const displayTimes = this.getStorage( 'times' ) || 0;

				this.setStorage( 'times', displayTimes + 1 );
			}

			/**
			 * Deliberately a no-op.
			 *
			 * The base Document runs every element handler on init. For a popup
			 * that would initialise carousels, forms and maps inside a hidden,
			 * zero-size element — sliders in particular measure to nothing and
			 * never recover. Handlers run in showModal() instead.
			 */
			runElementsHandlers() {}

			async onInit() {
				super.onInit();

				// Elementor can be configured to load the dialog library on
				// demand, in which case it is not there yet.
				if ( ! window.DialogsManager ) {
					await elementorFrontend.utils.assetsLoader.load( 'script', 'dialog' );
				}

				this.initModal();

				if ( this.isEdit ) {
					this.showModal();
					return;
				}

				/*
				 * `.show()` first, then `.remove()`.
				 *
				 * The stylesheet sets `display: none` on the printed wrapper.
				 * Clearing it here — before taking the outerHTML snapshot — is
				 * what makes the copy that gets inserted into the dialog visible.
				 * Remove without show and the popup opens to an invisible box.
				 *
				 * This detach is also why the popup markup is absent from the
				 * pixel harness's captures: capture.js reads `page.content()`,
				 * the post-JavaScript DOM, and by then the wrapper is gone. The
				 * popup is only ever in the *raw* server response.
				 */
				this.$element.show().remove();
				this.elementHTML = this.$element[ 0 ].outerHTML;

				if ( elementorFrontend.isEditMode() ) {
					return;
				}

				// Previewing the popup's own post shows it immediately.
				if ( elementorFrontend.isWPPreviewMode() && elementorFrontend.config.post.id === this.getSettings( 'id' ) ) {
					this.showModal();
					return;
				}

				this.startTiming();
			}

			onSettingsChange( model ) {
				const changedKey = Object.keys( model.changed )[ 0 ];

				if ( -1 !== changedKey.indexOf( 'entrance_animation' ) ) {
					this.setEntranceAnimation();
				}

				if ( 'exit_animation' === changedKey ) {
					this.setExitAnimation();
				}

				if ( 'close_button_position' === changedKey ) {
					this.setCloseButtonPosition();
				}
			}
		};

		/**
		 * The module object. Exposed as `window.piecyferPopup`.
		 *
		 * Pro's equivalent is `elementorProFrontend.modules.popup`. Nothing in the
		 * theme reads that path — only Pro's own form-action handler did — so a
		 * name of our own is safe and keeps the two from being confused during the
		 * transition.
		 */
		var piecyferPopup = {
			popupPopped: false,

			showPopup: function ( settings, event ) {
				var popup = elementorFrontend.documentsManager.documents[ settings.id ],
					modal;

				if ( ! popup ) {
					return;
				}

				modal = popup.getModal();

				if ( settings.toggle && modal.isVisible() ) {
					modal.hide();
					return;
				}

				popup.showModal( event );
			},

			closePopup: function ( settings, event ) {
				var popupID = $( event.target ).parents( '[data-elementor-type="popup"]' ).data( 'elementorId' ),
					popupDocument;

				if ( ! popupID ) {
					return;
				}

				popupDocument = elementorFrontend.documentsManager.documents[ popupID ];

				if ( ! popupDocument ) {
					return;
				}

				popupDocument.getModal().hide();

				if ( settings.do_not_show_again ) {
					popupDocument.disable();
				}
			},

			/**
			 * Page-view and session counters, for the `page_views` and `sessions`
			 * timing rules.
			 *
			 * Only maintained when the site actually has a popup, because this
			 * writes to the visitor's localStorage on every single page view.
			 */
			setViewsAndSessions: function () {
				var pageViews = elementorFrontend.storage.get( 'pageViews' ) || 0,
					activeSession,
					sessions;

				elementorFrontend.storage.set( 'pageViews', pageViews + 1 );

				activeSession = elementorFrontend.storage.get( 'activeSession', { session: true } );

				if ( activeSession ) {
					return;
				}

				elementorFrontend.storage.set( 'activeSession', true, { session: true } );

				sessions = elementorFrontend.storage.get( 'sessions' ) || 0;
				elementorFrontend.storage.set( 'sessions', sessions + 1 );
			},
		};

		window.piecyferPopup = piecyferPopup;

		/*
		 * Registered on `elementor/frontend/init`, which fires *before*
		 * `onDocumentLoaded()` constructs the documents manager
		 * (elementor/assets/js/frontend.js:565 vs :571). Any later and the
		 * manager would already have chosen the base Document class for the
		 * popup, and nothing would ever open.
		 */
		elementorFrontend.hooks.addAction(
			'elementor/frontend/documents-manager/init-classes',
			function ( documentsManager ) {
				documentsManager.addDocumentClass( 'popup', PopupDocument );
			}
		);

		/*
		 * `components:init` fires immediately after the documents manager exists,
		 * so the actions are in place before any click can reach them. This pair
		 * is what makes the six `#elementor-action:action=popup:open` button links
		 * work — on this site, the only way any popup opens.
		 */
		elementorFrontend.on( 'components:init', function () {
			elementorFrontend.utils.urlActions.addAction( 'popup:open', function ( settings, event ) {
				piecyferPopup.showPopup( settings, event );
			} );

			elementorFrontend.utils.urlActions.addAction( 'popup:close', function ( settings, event ) {
				piecyferPopup.closePopup( settings, event );
			} );
		} );

		if ( config.hasPopups && ! elementorFrontend.isEditMode() && ! elementorFrontend.isWPPreviewMode() ) {
			piecyferPopup.setViewsAndSessions();
		}
	} );
}( jQuery ) );
