/**
 * Interface Logic
 * Manages the "Application Shell" - sidebar, navigation, global frame interactions.
 *
 * Flow Overview:
 * 1. Initial Load: `initNavigation` checks URL for `tab` param or `#hash`.
 * 2. Tab Activation: `activateTab` updates UI, saves state, and triggers `App.loadTabSettings` (Lazy Load).
 * 3. Deep Linking: If a section ID is present (e.g. #tab/section), `scrollToElement` handles the jump.
 * 4. Dynamic Handling: Since fields load via AJAX, `scrollToElement` uses a retry mechanism to "wait" for elements.
 */
import { getCtcStorageItem, setCtcStorageItem } from '../core/Storage.js';
import { log } from '../core/Utils.js';

export default class Interface {
	static init ( app ) {
		log( 'Interface', 'Initializing...' );
		this.app = app;

		// Deep-link target, held until its tab has actually rendered.
		this.pendingTarget = null;

		// `activateTab` awaits `loadTabSettings`, but that returns early when a
		// load is already in flight (App.loadTabSettings), so awaiting it is not
		// proof the fields exist. `tab:changed` is emitted after the render.
		this.app.events?.on( 'tab:changed', ( tabId ) => {
			if ( ! this.pendingTarget ) { return; }

			// Also emitted for in-card sub-tabs; only panels carry a deep link.
			if ( ! document.getElementById( tabId )?.classList.contains( 'settings-panel' ) ) { return; }

			const target = this.pendingTarget;
			this.pendingTarget = null;
			this.scrollToElement( target );
		} );

		document.addEventListener( 'DOMContentLoaded', () => {
			this.initSidebar();
			this.initNavigation();
			this.initTabs();

			this.app.utils.initConditionalFieldLogic( document );

			this.initHelpIcons();
			this.initGreetingsImage();
			this.initRightSidebar();
		} );
	}

	/**
	 * Sidebar & Toggle Menu Logic
	 */
	static initSidebar () {
		const menuToggle = document.getElementById( 'menu-toggle' );
		const closeSidebar = document.getElementById( 'close-sidebar' );
		const sidebar = document.getElementById( 'sidebar' );
		const settingsToggle = document.getElementById( 'settings-toggle' );
		const settingsDropdown = document.getElementById( 'settings-dropdown' );

		this.desktopQuery = window.matchMedia( '(min-width: 768px)' );

		/*
		 * Desktop sidebar width is a preference, not a per-load default. It used
		 * to re-expand on every page load, so anyone who preferred the icon-only
		 * rail had to collapse it again after each save/reload. Expanded stays
		 * the default — only an explicit collapse is remembered.
		 */
		if ( this.desktopQuery.matches && sidebar ) {
			sidebar.classList.toggle( 'expanded', true !== getCtcStorageItem( 'sidebar-collapsed' ) );
		}

		this.syncMenuToggleState( menuToggle, sidebar );

		/*
		 * Every dismiss path for the mobile drawer — the close button, a click
		 * outside it, Escape — does the same three things. Stated once so a
		 * later change cannot be applied to two of the three and quietly
		 * missed on the third.
		 */
		const closeDrawer = () => {
			sidebar.classList.remove( 'open' );
			document.body.style.overflow = '';
			this.syncMenuToggleState( menuToggle, sidebar );
		};

		if ( menuToggle && sidebar ) {
			menuToggle.addEventListener( 'click', () => {
				if ( this.desktopQuery.matches ) {
					const isExpanded = sidebar.classList.toggle( 'expanded' );
					setCtcStorageItem( 'sidebar-collapsed', ! isExpanded );
				} else {
					sidebar.classList.toggle( 'open' );
					document.body.style.overflow = sidebar.classList.contains( 'open' ) ? 'hidden' : '';
				}
				this.syncMenuToggleState( menuToggle, sidebar );
			} );
		}

		if ( closeSidebar && sidebar ) {
			closeSidebar.addEventListener( 'click', () => {
				closeDrawer();
			} );
		}

		document.addEventListener( 'click', ( event ) => {
			if (
				! this.desktopQuery.matches &&
				sidebar &&
				menuToggle &&
				! sidebar.contains( event.target ) &&
				! menuToggle.contains( event.target ) &&
				sidebar.classList.contains( 'open' )
			) {
				closeDrawer();
			}
		} );

		// Esc closes the mobile drawer, matching every other overlay on the page.
		document.addEventListener( 'keydown', ( event ) => {
			if ( 'Escape' !== event.key || ! sidebar ) { return; }
			if ( this.desktopQuery.matches || ! sidebar.classList.contains( 'open' ) ) { return; }

			closeDrawer();
			menuToggle?.focus();
		} );

		// The breakpoint can be crossed by a resize/rotate, which swaps which
		// class ('expanded' vs 'open') the toggle is reporting on.
		this.desktopQuery.addEventListener( 'change', () =>
			this.syncMenuToggleState( menuToggle, sidebar ) );

		if ( settingsToggle && settingsDropdown ) {
			const setDropdown = ( open ) => {
				settingsDropdown.classList.toggle( 'hidden', ! open );
				settingsToggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			};

			settingsToggle.addEventListener( 'click', ( event ) => {
				event.stopPropagation();
				setDropdown( settingsDropdown.classList.contains( 'hidden' ) );
			} );
			document.addEventListener( 'click', ( event ) => {
				if (
					! settingsDropdown.contains( event.target ) &&
					! settingsToggle.contains( event.target )
				) {
					setDropdown( false );
				}
			} );

			// Esc closes the dropdown and returns focus to the button that opened it.
			document.addEventListener( 'keydown', ( event ) => {
				if ( 'Escape' !== event.key || settingsDropdown.classList.contains( 'hidden' ) ) { return; }
				setDropdown( false );
				settingsToggle.focus();
			} );
		}
	}

	/**
	 * Keep the hamburger's ARIA state and tooltip in step with the sidebar.
	 *
	 * The button is icon-only, so `aria-expanded` is the only thing telling a
	 * screen reader what the click did, and the tooltip is its sighted
	 * equivalent. Desktop toggles the labels (`expanded`), mobile slides the
	 * whole drawer (`open`) — one attribute covers both, because from the
	 * user's side it is the same question: is the menu showing?
	 *
	 * @param {HTMLElement|null} menuToggle The hamburger button.
	 * @param {HTMLElement|null} sidebar    The sidebar it controls.
	 */
	static syncMenuToggleState ( menuToggle, sidebar ) {
		if ( ! menuToggle || ! sidebar ) { return; }

		const isOpen = this.desktopQuery.matches ?
			sidebar.classList.contains( 'expanded' ) :
			sidebar.classList.contains( 'open' );

		menuToggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		menuToggle.setAttribute( 'data-tip', isOpen ? 'Collapse menu' : 'Expand menu' );
	}

	/**
	 * Main Navigation & Deep Link Handling
	 */
	static initNavigation () {
		log( 'Interface', 'Initializing Navigation...' );
		const navItems = document.querySelectorAll( '.nav-item' );

		// 1. Determine Initial Active Tab
		// Order of priority: URL Parameter (?tab=) > URL Hash (#tab) > LocalStorage > Default (General)
		const urlParams = new URLSearchParams( window.location.search );
		const urlTabId = urlParams.get( 'tab' );
		const hashTabId = window.location.hash.replace( '#', '' )
			.split( '/' )[ 0 ];

		const activeTabId = urlTabId || hashTabId || getCtcStorageItem( 'active-tab' );

		// 2. Initial Activation
		const activeTab = activeTabId ?
			document.querySelector( `.nav-item[data-tab="${activeTabId}"]` ) :
			null;
		const activePanel = activeTabId ? document.getElementById( activeTabId ) : null;

		if ( activeTab && activePanel ) {
			const initialSectionId = window.location.hash.replace( '#', '' )
				.split( '/' )[ 1 ];

			// Queue the target; the `tab:changed` handler jumps once rendered.
			this.pendingTarget = initialSectionId || null;

			// If we have a section ID (deep link), we pass `skipScroll=true` to avoid jumping to top
			this.activateTab( activeTabId, !! initialSectionId );
		} else {
			const defaultActive = document.querySelector( '.nav-item.active' );
			if ( defaultActive ) {
				this.activateTab( defaultActive.getAttribute( 'data-tab' ) );
			}
		}

		Interface.updateMobileSectionLabel();

		// 3. Tab Click Listeners
		navItems.forEach( ( item ) => {
			item.addEventListener( 'click', () => {
				this.activateTab( item.getAttribute( 'data-tab' ) );
			} );
		} );

		// 4. Logo / Home Click Logic
		// The logo is a <div role="button" tabindex="0">, so it has to answer
		// Enter/Space itself — a real <button> would inherit that for free, but
		// this one wraps the mark and wordmark and is styled as a block.
		const logoHome = document.getElementById( 'logo-home' );
		if ( logoHome ) {
			const goHome = () => {
				const generalTab = document.querySelector( '.nav-item[data-tab="general-settings"]' );
				if ( generalTab ) { generalTab.click(); }
			};

			logoHome.addEventListener( 'click', goHome );
			logoHome.addEventListener( 'keydown', ( event ) => {
				if ( 'Enter' !== event.key && ' ' !== event.key ) { return; }

				// Space would otherwise scroll the page out from under the click.
				event.preventDefault();
				goHome();
			} );
		}

		// 5. Dynamic Module Scroll Synchronization
		// Some sections are created ONLY when they enter the viewport (Lazy Sections)
		document.addEventListener( 'ht_ctc_register_section_dynamic', ( event ) => {
			const { element, id } = event.detail;
			if ( element && id && 'IntersectionObserver' in window ) {
				const observer = new IntersectionObserver( ( entries, obs ) => {
					entries.forEach( entry => {
						if ( entry.isIntersecting ) {
							this.app.loadTabSettings( id );
							obs.unobserve( entry.target );
						}
					} );
				}, { rootMargin: '200px' } );
				observer.observe( element );
			} else if ( id ) {
				this.app.loadTabSettings( id );
			}
		} );

		// 6. Global Link Interceptor (Deep Links)
		// Internal clicks on <a href="#tab-id/section-id">
		document.addEventListener( 'click', async ( event ) => {
			const link = event.target.closest( 'a[href^="#"]' );
			if ( ! link ) { return; }

			const fullHash = link.getAttribute( 'href' )
				.replace( '#', '' );
			if ( ! fullHash ) { return; }

			const [ tabId, sectionId ] = fullHash.split( '/' );
			const navItem = document.querySelector( `.nav-item[data-tab="${tabId}"]` );

			if ( navItem ) {
				event.preventDefault();

				// Queue the target; the `tab:changed` handler jumps once rendered.
				this.pendingTarget = sectionId || null;

				// Switch tab (and prevent default scroll-to-top if we are targeting a sub-section)
				await this.activateTab( tabId, !! sectionId );
			}
		} );

		// 7. Drill-Down Menu Logic (Sidebar Submenus)
		document.addEventListener( 'click', ( event ) => {
			const drillDownBtn = event.target.closest( '.drill-down-btn' );
			const backBtn = event.target.closest( '.drill-down-back-btn' );

			if ( drillDownBtn ) {
				const targetMenuId = drillDownBtn.getAttribute( 'data-target' );
				const targetMenu = document.getElementById( targetMenuId );
				if ( targetMenu ) {
					document.querySelectorAll( '.sidebar-menu' )
						.forEach( menu => menu.classList.remove( 'active' ) );
					targetMenu.classList.add( 'active' );
					( targetMenu.querySelector( '.nav-item.active' ) || targetMenu.querySelector( '.nav-item' ) )?.click();
				}
			}

			if ( backBtn ) {
				const targetMenuId = backBtn.getAttribute( 'data-target' );
				const targetMenu = document.getElementById( targetMenuId );
				if ( targetMenu ) {
					document.querySelectorAll( '.sidebar-menu' )
						.forEach( menu => menu.classList.remove( 'active' ) );
					targetMenu.classList.add( 'active' );
					( targetMenu.querySelector( '.nav-item.active' ) || targetMenu.querySelector( '.nav-item' ) )?.click();
				}
			}
		} );
	}

	/**
	 * Activates a settings tab.
	 *
	 * Flow:
	 * 1. UI Switch (CSS classes)
	 * 2. Persistence (LocalStorage)
	 * 3. Scroll Management (Reset to top or stay put)
	 * 4. Data Loading (Fetch fields via API if not loaded)
	 *
	 * @param {string} tabId - The ID of the tab to activate.
	 * @param {boolean} skipScroll - If true, keeps the current scroll position (used for deep linking).
	 */
	static async activateTab ( tabId, skipScroll = false ) {
		const navItem = document.querySelector( `.nav-item[data-tab="${tabId}"]` );
		const panel = document.getElementById( tabId );
		if ( ! navItem || ! panel ) { return; }

		// UI/State Updates. `aria-current` carries the same meaning as the
		// `active` class for anyone who can't see which item is tinted.
		document.querySelectorAll( '.nav-item' )
			.forEach( nav => {
				nav.classList.remove( 'active' );
				nav.removeAttribute( 'aria-current' );
			} );
		document.querySelectorAll( '.settings-panel' )
			.forEach( panel => panel.classList.remove( 'active' ) );

		navItem.classList.add( 'active' );
		navItem.setAttribute( 'aria-current', 'page' );
		panel.classList.add( 'active' );
		setCtcStorageItem( 'active-tab', tabId );

		// Drill-down menu sync
		const parentMenu = navItem.closest( '.sidebar-menu' );
		if ( parentMenu ) {
			document.querySelectorAll( '.sidebar-menu' )
				.forEach( menu => menu.classList.remove( 'active' ) );
			parentMenu.classList.add( 'active' );
		}

		// Scroll management
		if ( ! skipScroll ) {
			Interface.resetScrollTo();
		}

		// Lazy Load content
		if ( panel.dataset.loaded === 'false' ) {
			await Interface.app.loadTabSettings( tabId );
		}

		Interface.updateMobileSectionLabel();
		Interface.updateProWidget( tabId );

		Interface.app.events?.emit( 'tab:changed', tabId );

		// Mobile behavior: close sidebar after selection.
		// The hamburger has to be told, same as every other dismiss path — this
		// one sits outside initSidebar so it cannot use its closeDrawer helper,
		// and without the sync it reported aria-expanded="true" (and offered
		// "Collapse menu") over an already-closed drawer. It is the most common
		// mobile gesture there is: open the menu, pick a section.
		const sidebar = document.getElementById( 'sidebar' );
		if ( ! Interface.desktopQuery.matches && sidebar ) {
			sidebar.classList.remove( 'open' );
			document.body.style.overflow = '';
			Interface.syncMenuToggleState( document.getElementById( 'menu-toggle' ), sidebar );
		}
	}

	/**
	 * Scrolls to an element inside the active panel and highlights it.
	 *
	 * Callers must have the target's panel active already — the `tab:changed`
	 * handler in `init()` waits for the render, and SettingsManager awaits
	 * `activateTab` before calling this for a failed field.
	 *
	 * Two things here are load-bearing:
	 *
	 * 1. **The lookup is scoped to the panel.** Field ids repeat across panels
	 *    (`side_1`, `same_settings` exist in general/group/share), so a global
	 *    `getElementById` returns whichever copy comes first in the document —
	 *    always the general-settings one — and a link into another tab would
	 *    resolve to the wrong panel.
	 * 2. **`scrollIntoView`, not a container scroll.** `.main-content` has
	 *    `overflow-y: auto` but is never height-constrained, so it never
	 *    scrolls — the window does, with the header and sidebars sticky over
	 *    it. Scrolling `.main-content` explicitly is a silent no-op.
	 *    The offset clearing the sticky header is `scroll-margin-top` in CSS.
	 *
	 * Targets hidden by a collapsed accordion or an inactive sub-tab are not
	 * handled yet — they resolve but cannot be scrolled to.
	 *
	 * @param {string} elementId - The ID of the target element (Field ID, Card ID, etc.)
	 */
	static scrollToElement ( elementId ) {
		const panel = document.querySelector( '.settings-panel.active' );
		if ( ! panel || ! elementId ) { return; }

		// Compared rather than used as a `#id` selector: ids are not always valid
		// CSS identifiers (`channels][whatsapp][enable`) and would throw.
		const element = [ ...panel.querySelectorAll( '[id]' ) ]
			.find( ( el ) => el.id === elementId );

		if ( ! element ) {
			log( 'Interface', `scrollToElement: #${elementId} not found in ${panel.id}.` );
			return;
		}

		// Scroll to the wrapper group so the label and help text come along.
		const target = element.closest( '.form-group' ) ||
			element.closest( '.ctc-card' ) ||
			element.closest( '.field-group' ) ||
			element;

		target.scrollIntoView( { behavior: 'smooth', block: 'start' } );

		target.classList.add( 'ctc-highlight-jump' );
		setTimeout( () => target.classList.remove( 'ctc-highlight-jump' ), 2000 );
	}

	/**
	 * Inner Page Tabs Delegation (e.g. Settings within a Card)
	 */
	static initTabs () {
		document.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '.tab-button' );
			if ( ! button || button.classList.contains( 'nav-item' ) ) { return; }

			const tabContainer = button.closest( '.tabs' );
			if ( ! tabContainer ) { return; }

			event.preventDefault();
			const tabToShow = button.getAttribute( 'data-tab' );

			tabContainer.querySelectorAll( '.tab-button' )
				.forEach( btn => {
					btn.classList.remove( 'active' );
					btn.setAttribute( 'aria-selected', 'false' );
				} );
			button.classList.add( 'active' );
			button.setAttribute( 'aria-selected', 'true' );

			tabContainer.querySelectorAll( '.tab-content' )
				.forEach( content => content.classList.remove( 'active' ) );

			const targetContent = document.getElementById( `${tabToShow}-tab` );
			if ( targetContent ) { targetContent.classList.add( 'active' ); }

			Interface.app.events?.emit( 'tab:changed', tabToShow );
		} );
	}

	/**
	 * Adaptive Help Icons Logic
	 */
	static initHelpIcons () {
		document.addEventListener( 'click', ( event ) => {
			const toggle = event.target.closest( '.help-toggle' );
			if ( ! toggle ) { return; }

			event.preventDefault();
			const parent = toggle.closest( '.form-group' );
			if ( parent ) {
				parent.classList.toggle( 'help-active' );
			}
		} );
	}

	/**
	 * WordPress Media Uploader integration
	 *
	 * @todo Refactor and move this event binding directly inside BlockUploadImage.js to make the component self-contained.
	 */
	static initGreetingsImage () {
		let mediaUploader;
		document.addEventListener( 'click', ( event ) => {
			const addBtn = event.target.closest( '.ctc_add_image_wp' );
			const removeBtn = event.target.closest( '.ctc_remove_image_wp' );

			if ( addBtn ) {
				event.preventDefault();
				if ( mediaUploader ) { mediaUploader.open(); return; }
				if ( ! window.wp?.media ) { return; }

				mediaUploader = wp.media.frames.file_frame = wp.media( {
					title: 'Select Header Image',
					button: { text: 'Select' },
					multiple: false,
				} );

				mediaUploader.on( 'select', () => {
					const attachment = mediaUploader.state()
						.get( 'selection' )
						.first()
						.toJSON();
					if ( ! attachment ) { return; }
					const wrapper = addBtn.closest( '.ctc-image-upload-wrapper' ) || document;
					const input = wrapper.querySelector( '.g_header_image' );
					const preview = wrapper.querySelector( '.g_header_image_preview' );
					const remBtn = wrapper.querySelector( '.ctc_remove_image_wp' );

					if ( input ) {
						input.value = attachment.url;
						input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
					if ( preview ) { preview.src = attachment.url; preview.style.display = 'block'; }
					if ( remBtn ) { remBtn.style.display = 'inline-block'; }
				} );
				mediaUploader.open();
			}

			if ( removeBtn ) {
				event.preventDefault();
				const wrapper = removeBtn.closest( '.ctc-image-upload-wrapper' ) || document;
				const input = wrapper.querySelector( '.g_header_image' );
				const preview = wrapper.querySelector( '.g_header_image_preview' );
				if ( input ) {
					input.value = '';
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
				if ( preview ) { preview.style.display = 'none'; }
				removeBtn.style.display = 'none';
			}
		} );
	}

	/**
	 * Resets scroll position to top
	 */
	static resetScrollTo () {
		const target = document.querySelector( '.main-content' );
		if ( target ) { target.scrollTo( 0, 0 ); }
		window.scrollTo( 0, 0 );
	}

	/**
	 * Updates the current section label (Mobile Top Bar)
	 */
	static updateMobileSectionLabel () {
		const label = document.getElementById( 'mobile-section-label' );
		const activeNav = document.querySelector( '.nav-item.active' );
		if ( ! label || ! activeNav ) { return; }

		const span = activeNav.querySelector( 'span:not(.dashicons):not(.ctc-icon)' );
		label.textContent = span ? span.textContent.trim() : activeNav.textContent.trim();
	}

	/**
	 * PRO widget (free version, right sidebar): show the feature items
	 * relevant to the active tab. Each <li data-tabs="..."> lists the nav-tab
	 * ids it belongs to; when none match the active tab, the items tagged
	 * `default` are shown instead. Widget markup exists only without PRO.
	 *
	 * On the 'pro-features' tab the whole widget is hidden — the full PRO
	 * features page is already on screen, so the sidebar teaser is redundant.
	 *
	 * @param {string} tabId - The activated nav tab id (e.g. 'greetings-settings').
	 */
	static updateProWidget ( tabId ) {
		const promoWidget = document.querySelector( '.ctc-pro-promo' );
		if ( ! promoWidget ) { return; }

		// Redundant on the PRO features page itself; show it everywhere else.
		promoWidget.hidden = ( tabId === 'pro-features' );
		if ( promoWidget.hidden ) { return; }

		const items = promoWidget.querySelectorAll( '.ctc-pro-feature-list li[data-tabs]' );
		if ( ! items.length ) { return; }

		const matches = ( li, key ) => li.dataset.tabs.split( ' ' )
			.includes( key );
		const hasMatch = [ ...items ].some( li => matches( li, tabId ) );

		items.forEach( li => {
			li.hidden = ! matches( li, hasMatch ? tabId : 'default' );
		} );
	}

	/**
	 * Right Sidebar Tabs (Support / Feedback / Preview)
	 *
	 * A collapsible tablist: clicking the open tab closes the panel entirely,
	 * so "no tab selected" is a real state here, not just an in-between.
	 *
	 * The markup declares role="tablist"/role="tab", which means `aria-selected`
	 * and the roving `tabindex` — not the CSS classes — are what assistive tech
	 * and the Tab key actually read. Toggling classes alone left the ARIA frozen
	 * at whatever PHP printed, so Support was announced as the selected tab for
	 * the life of the page no matter which one was showing, and Tab still landed
	 * on all three buttons. `render()` is the single place that moves both.
	 */
	static initRightSidebar () {
		const tabButtons = [ ...document.querySelectorAll( '.sidebar-tab-btn' ) ];
		const tabContents = [ ...document.querySelectorAll( '.sidebar-tab-content' ) ];
		if ( ! tabButtons.length ) { return; }

		const idOf = ( btn ) => btn.dataset.sidebarTab;

		// Which button stays tabbable while the panel is closed — without it the
		// whole tablist drops out of the tab order and can't be reopened by keyboard.
		let lastOpenId = idOf( tabButtons.find( btn => btn.classList.contains( 'active' ) ) || tabButtons[ 0 ] );

		/**
		 * @param {string|null} tabId Tab to open, or null to close the panel.
		 */
		const render = ( tabId ) => {
			if ( tabId ) { lastOpenId = tabId; }

			tabButtons.forEach( btn => {
				const isOpen = idOf( btn ) === tabId;
				btn.classList.toggle( 'active', isOpen );
				btn.setAttribute( 'aria-selected', isOpen ? 'true' : 'false' );

				// role="tab" supports aria-expanded; it is what distinguishes
				// "this tab is current" from "its panel is on screen".
				btn.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
				btn.tabIndex = ( idOf( btn ) === lastOpenId ) ? 0 : -1;
			} );

			tabContents.forEach( content =>
				content.classList.toggle( 'active', content.id === `sidebar-tab-${tabId}` ) );
		};

		const switchTab = ( tabId ) => {
			const isOpen = tabButtons.some( btn => idOf( btn ) === tabId && btn.classList.contains( 'active' ) );
			render( isOpen ? null : tabId );
		};

		/**
		 * Resolve an arrow/Home/End press to the button focus should move to.
		 *
		 * The tablist is laid out in a row, so Left/Right are the arrows that
		 * apply; in RTL the visual order is mirrored, so the step is too.
		 *
		 * @param {KeyboardEvent} event
		 * @param {number}        index Index of the button currently focused.
		 * @returns {HTMLElement|null}
		 */
		const nextFromKey = ( event, index ) => {
			if ( 'Home' === event.key ) { return tabButtons[ 0 ]; }
			if ( 'End' === event.key ) { return tabButtons[ tabButtons.length - 1 ]; }

			let step = 0;
			if ( 'ArrowRight' === event.key ) { step = 1; }
			if ( 'ArrowLeft' === event.key ) { step = -1; }
			if ( ! step ) { return null; }

			if ( 'rtl' === ( document.documentElement.dir || '' ) ) { step = -step; }

			const total = tabButtons.length;
			return tabButtons[ ( index + step + total ) % total ];
		};

		tabButtons.forEach( ( btn, index ) => {
			btn.addEventListener( 'click', () => switchTab( idOf( btn ) ) );

			btn.addEventListener( 'keydown', ( event ) => {
				const target = nextFromKey( event, index );
				if ( ! target ) { return; }

				// Otherwise Home/End jump the page and the arrows scroll it.
				event.preventDefault();

				// Follow-focus activation, same as the APG's automatic-activation
				// pattern: the panel under the arrows is always the one showing,
				// so keyboard and pointer end up in the same place.
				render( idOf( target ) );
				target.focus();
			} );
		} );

		// Programmatic activation (e.g. PreviewManager surfaces a preview note
		// while another tab — or none — is showing). Unlike a click, this never
		// toggles the panel closed: it only makes the requested tab visible.
		document.addEventListener( 'ctc_open_sidebar_tab', ( event ) => {
			const tabId = event.detail?.tab;
			if ( ! tabId ) { return; }
			const targetBtn = tabButtons.find( btn => idOf( btn ) === tabId );
			if ( ! targetBtn || targetBtn.classList.contains( 'active' ) ) { return; }
			render( tabId );
		} );

		// Bring the ARIA in line with whatever PHP rendered as active.
		render( idOf( tabButtons.find( btn => btn.classList.contains( 'active' ) ) || tabButtons[ 0 ] ) );
	}
}
