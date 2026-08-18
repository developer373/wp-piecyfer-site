/**
 * Generic Contextual Panel Component Renderer
 *
 * Renders an inline contextual settings panel drawer in the container after the active
 * row/element, when any card, button, or option is selected.
 * Works universally for styles, greetings, forms, positions, WooCommerce, etc.
 *
 * ONE PANEL PER PAGE, moved to whichever container asks for it. The module is imported
 * once, so this state is the page's — that makes "one panel" structural rather than a
 * convention every caller has to honour.
 *
 * Retained cards and id parking are the two non-obvious parts: a card is hidden rather
 * than re-rendered because it holds unsaved edits, and a hidden card's ids are parked so
 * that only one card is addressable at a time. Full rationale in
 * dev/docs/developer-reference/admin2-contextual-panel.md.
 */
import { getSafeProperty } from '../../core/Utils.js';

/** Fade duration in ms; keep in step with the .ctc-contextual-panel transition. */
const FADE_MS = 250;

/** The single panel element, built on first open. Nothing in it changes per item. */
let panel = null;

/** `group:id` -> the card element holding that item's rendered fields. */
const cards = new Map();

/** Which `group:id` the panel is currently showing, or null when collapsed. */
let openKey = null;

/**
 * WHERE it is currently showing it — the container it was placed in.
 *
 * Tracked alongside openKey because the same item is offered by more than one picker, so
 * the key alone does not say whether a press means "close this" or "bring it here".
 * Recorded rather than read back off `panel.parentElement`: placePanel() inserts after a
 * row anchor that is not always a direct child of the container it was asked for.
 */
let openContainer = null;

let collapseTimer = null;

/** The Escape handler is bound once for the page, on the first open. */
let escapeBound = false;

/**
 * Move a subtree's ids out of the way, or put them back.
 *
 * Reversible and narrow: names, values and data-changed are left alone, so a parked card
 * still submits its unsaved edits.
 *
 * @param {Element} root Card element.
 * @param {boolean} park True to park, false to restore.
 */
const shiftIds = ( root, park ) => {
	const fromAttr = park ? 'id' : 'data-ctc-cx-id';
	const toAttr = park ? 'data-ctc-cx-id' : 'id';

	root.querySelectorAll( `[${fromAttr}]` )
		.forEach( el => {
			el.setAttribute( toAttr, el.getAttribute( fromAttr ) );
			el.removeAttribute( fromAttr );
		} );

	const fromFor = park ? 'for' : 'data-ctc-cx-for';
	const toFor = park ? 'data-ctc-cx-for' : 'for';

	root.querySelectorAll( `label[${fromFor}]` )
		.forEach( el => {
			el.setAttribute( toFor, el.getAttribute( fromFor ) );
			el.removeAttribute( fromFor );
		} );

	// The card's own id too — it is the scope `#style_2 #cta_type` resolves through.
	if ( park && root.id ) {
		root.setAttribute( 'data-ctc-cx-id', root.id );
		root.removeAttribute( 'id' );
	} else if ( ! park && root.hasAttribute( 'data-ctc-cx-id' ) ) {
		root.id = root.getAttribute( 'data-ctc-cx-id' );
		root.removeAttribute( 'data-ctc-cx-id' );
	}
};

/**
 * Build the panel once.
 *
 * @returns {Element} The panel.
 */
const buildPanel = () => {
	if ( panel ) { return panel; }

	// Deliberately NOT .ctc-card — `.ctc-admin-dashboard .ctc-card` outranks it and
	// would impose a 520px max-width the panel must not have.
	panel = document.createElement( 'div' );
	panel.className = 'ctc-contextual-panel';
	panel.hidden = true;

	// Stable id for the trigger's aria-controls; stays correct as the panel moves.
	panel.id = 'ctc-contextual-panel';

	// A bare container — heading and fields both belong to the CARD, so the panel
	// holds no per-item state that could fall out of step with what it shows.
	return panel;
};

/**
 * Render an item's fields into a card, once. Later opens reuse it.
 *
 * @param {Object} app                 Main App instance.
 * @param {string} key                 `group:id`.
 * @param {string} contextualId        Item identifier, used as the card's DOM scope.
 * @param {Object} config       The item's definition from the group payload.
 * @returns {Element} The card element.
 */
const ensureCard = ( app, key, contextualId, config ) => {
	const existing = cards.get( key );
	if ( existing ) { return existing; }

	const card = document.createElement( 'div' );
	card.className = 'ctc-contextual-card';

	// The ARIA equivalent of fieldset/legend: these fields are only meaningful as
	// "Style 2's", and ids like #cta_type repeat across items.
	card.setAttribute( 'role', 'group' );

	// Diagnostic only. A parked card has had its id removed, so in devtools this is
	// the only thing identifying which item a hidden card belongs to.
	card.dataset.contextualKey = key;
	card.hidden = true;

	// The item id is a DOM SCOPE, not just a label: field declarations disambiguate
	// with selectors like `#style_2 #cta_type`, which need this element to exist.
	card.id = contextualId;

	panel.appendChild( card );

	// Written once with the card rather than per open, so a retained card carries its
	// own heading and the two cannot get out of step.
	const headerEl = document.createElement( 'div' );
	headerEl.className = 'ctc-contextual-header';

	const titleEl = document.createElement( 'h3' );

	// shiftIds() parks this id too, so a parked card's aria-labelledby points at
	// nothing until it is shown again — harmless, since a hidden card is not exposed.
	titleEl.id = `${contextualId}-title`;
	titleEl.textContent = config.title || contextualId;

	const descEl = document.createElement( 'p' );
	descEl.textContent = config.desc || '';
	descEl.hidden = ! config.desc;

	headerEl.append( titleEl, descEl );

	card.appendChild( headerEl );
	card.setAttribute( 'aria-labelledby', titleEl.id );

	/*
	 * No close control in the header: the trigger that opened the panel is the one that
	 * closes it, and it swaps its icon to an up-arrow while expanded.
	 */
	const contentEl = document.createElement( 'div' );
	contentEl.className = 'ctc-contextual-content';

	const fragment = document.createDocumentFragment();

	config.fields.forEach( fieldConfig => {
		const el = app.createFieldElement( fieldConfig );
		if ( el ) {
			fragment.appendChild( el );
		}
	} );

	contentEl.appendChild( fragment );
	card.appendChild( contentEl );

	/*
	 * Per-item caveat — e.g. "a style's settings are the style's, shared by every picker
	 * offering it". Declared per ITEM because it is not true of every group, and placed
	 * BELOW the fields: a caveat does not outrank the controls it qualifies.
	 * See dev/docs/developer-reference/admin2-contextual-panel.md.
	 */
	if ( config.note ) {
		const noteEl = document.createElement( 'p' );
		noteEl.className = 'ctc-contextual-note';

		const noteIcon = document.createElement( 'span' );
		noteIcon.className = 'dashicons dashicons-info-outline';
		noteIcon.setAttribute( 'aria-hidden', 'true' );

		const noteText = document.createElement( 'span' );
		noteText.textContent = config.note;

		noteEl.append( noteIcon, noteText );
		card.appendChild( noteEl );
	}

	// Parked from birth — showOnly() unparks whichever card is being shown.
	shiftIds( card, true );

	cards.set( key, card );

	return card;
};

/**
 * Whether a card's ids are currently parked.
 *
 * Read from the DOM rather than tracked alongside `hidden`, because the two are
 * deliberately not in step: collapsing parks immediately but defers hiding until the fade
 * has run. A card is parked exactly when shiftIds() has moved its own id aside.
 *
 * @param {Element} card Card element.
 * @returns {boolean} True when parked.
 */
const isParked = ( card ) => card.hasAttribute( 'data-ctc-cx-id' );

/**
 * Show one card and park every other. Pass null to park them all.
 *
 * @param {Object}      app Main App instance.
 * @param {string|null} key Which card to show.
 */
const showOnly = ( app, key ) => {
	let shown = null;

	cards.forEach( ( card, cardKey ) => {
		const show = cardKey === key;

		if ( show ) { shown = card; }

		// Guarded on parked-ness, not on `hidden` — shiftIds() is not idempotent, and
		// during a collapse fade a card is already parked while still visible.
		if ( show === isParked( card ) ) { shiftIds( card, ! show ); }

		card.hidden = ! show;
	} );

	if ( ! shown ) { return; }

	/*
	 * Wire (and re-evaluate) the conditional data_watch fields. Nothing else does this
	 * for a panel, and it must run after unparking — Conditional.js resolves watched
	 * controllers globally. Safe to repeat; binding is guarded per controller.
	 *
	 * Through safeRun for the same reason App.js inits managers that way: this is the
	 * last step of an open that has ALREADY shown the panel and set openKey. A throw
	 * here would skip syncTriggerState() back in openFor(), leaving an open panel whose
	 * trigger still reads aria-expanded="false" — and, being inside an async chain, it
	 * would surface as an unhandled rejection rather than anything the user can act on.
	 */
	app.utils.safeRun(
		() => app.utils.initConditionalFieldLogic( shown ),
		'Contextual panel conditional fields',
	);
};

/**
 * How many columns the container is currently laying out, or 0 when it is not a grid.
 *
 * Read from grid-template-columns, never measured: the panel is itself a full-width grid
 * child, so placing it changes the row boundaries a measurement would then read. The
 * computed value is a resolved track list, so its length is the count; bracketed line
 * names are not tracks and are dropped.
 *
 * @param {HTMLElement} container The container.
 * @returns {number} Column count, or 0 if the container is not a grid.
 */
const columnCount = ( container ) => {
	const tracks = window.getComputedStyle( container ).gridTemplateColumns;

	if ( ! tracks || 'none' === tracks ) { return 0; }

	return tracks.trim()
		.split( /\s+/ )
		.filter( token => ! token.startsWith( '[' ) ).length;
};

/**
 * The element the panel should sit after: the last one in the clicked element's row.
 *
 * Two strategies, because this component is not grid-only. On a grid the row is
 * arithmetic over source order — auto-placement fills in that order, so no layout needs to
 * be read at all. Anywhere else there is no such structure, so fall back to grouping by
 * vertical position.
 *
 * @param {HTMLElement} container The parent container element.
 * @param {HTMLElement} element   The element that was clicked.
 * @param {number}      columns   Column count from columnCount().
 * @returns {HTMLElement} The anchor to insert after.
 */
const rowAnchor = ( container, element, columns ) => {
	if ( columns > 0 ) {
		// Grid placement follows the direct children, so that is what the index is over.
		const items = Array.from( container.children )
			.filter( el => el !== panel );

		// The click may land on a descendant; walk up to the child the grid actually places.
		let item = element;
		while ( item && item.parentElement !== container ) { item = item.parentElement; }

		const index = item ? items.indexOf( item ) : -1;

		if ( index >= 0 ) {
			const endOfRow = ( Math.ceil( ( index + 1 ) / columns ) * columns ) - 1;
			return items[ Math.min( endOfRow, items.length - 1 ) ];
		}
	}

	const members = Array.from( container.querySelectorAll( '.grid-option, .ctc-card, .ctc-item' ) )
		.filter( el => el !== panel && ! panel.contains( el ) );
	const top = element.offsetTop;
	const row = members.filter( el => el.offsetTop === top );

	return row[ row.length - 1 ] || element;
};

/**
 * Move the panel to sit after the last element of the row holding the clicked element.
 *
 * @param {HTMLElement} container The parent container element.
 * @param {HTMLElement} element   The element that was clicked.
 */
const placePanel = ( container, element ) => {
	// A trigger rendered INSIDE the panel asks to open beneath itself; `.after()` on a
	// descendant throws HierarchyRequestError. Leave the panel put and just swap cards.
	if ( panel.contains( container ) ) { return; }

	// Out first: as a full-width grid child it would otherwise be counted as an item,
	// and would have shifted the row boundaries about to be read.
	if ( panel.parentElement === container ) { panel.remove(); }

	rowAnchor( container, element, columnCount( container ) )
		.after( panel );
};

/**
 * Collapse the panel. Cards are kept — they hold unsaved edits.
 */
const collapse = () => {
	if ( ! panel ) { return; }

	openKey = null;
	openContainer = null;
	panel.classList.remove( 'is-open' );

	// Announced rather than reaching into the picker's markup: the panel does not know
	// what opened it, and Escape can close it with the picker not involved at all.
	document.dispatchEvent( new CustomEvent( 'ctc_contextual_panel_closed' ) );

	// Park now, hide after the fade. Deferring the park would leave page-global ids in
	// a collapsed panel long enough for another tab's same-named fields to hit them.
	cards.forEach( card => {
		if ( ! isParked( card ) ) { shiftIds( card, true ); }
	} );

	clearTimeout( collapseTimer );
	collapseTimer = setTimeout( () => {
		// Reopened during the fade — leave it alone.
		if ( openKey ) { return; }

		panel.hidden = true;
		cards.forEach( card => { card.hidden = true; } );
	}, FADE_MS );
};

/**
 * Escape closes the panel, once, for the page.
 *
 * Deliberately NOT outside-click-to-close: the panel holds unsaved edits, so a stray
 * click dismissing it would read as losing them. Escape is explicit enough not to happen
 * by accident, and it reaches the panel from anywhere inside it — which the trigger does
 * not, once a long panel has been scrolled past.
 */
const initEscapeGuard = () => {
	if ( escapeBound ) { return; }
	escapeBound = true;

	// Nothing is stopped here: the page's other Escape handlers (sidebar, dropdown,
	// toast) each guard on their own state, so they are undisturbed.
	document.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' !== event.key || ! openKey ) { return; }
		collapse();
	} );
};

/**
 * Open the contextual panel for one item, moving it to that item's container.
 *
 * Always opens: re-showing the item that is already open is a legitimate call, because
 * the SAME item can be reached from two places — the Desktop and Mobile style grids offer
 * the same eleven styles — and the panel has to move to whichever one asked for it.
 *
 * @param {Object} app - Main App instance.
 * @param {string} contextualGroup - Group slug (data-contextual-group, e.g. 'contextual_styles').
 * @param {string} contextualId - Item identifier (data-contextual-id, e.g. 'style_2').
 * @param {HTMLElement} element - The DOM element clicked (option, card, button).
 * @param {HTMLElement} container - The parent container element.
 * @returns {Promise<boolean>} True if the panel is now open, false if it could not open.
 * Callers use it to keep a trigger's aria-expanded honest.
 */
export const showContextualPanel = async (
	app,
	contextualGroup,
	contextualId,
	element,
	container,
) => {
	if ( ! app || ! contextualGroup || ! contextualId || ! container || ! element ) {
		return false;
	}

	const key = `${contextualGroup}:${contextualId}`;

	// 1. Retrieve contextual group data from cache / REST store
	let contextualGroupData = null;
	try {
		contextualGroupData = await app.getFieldsForGroup( contextualGroup );
	} catch ( error ) {
		console.error( 'CTC: Failed to fetch contextual fields', contextualGroup, error );
		return false;
	}

	/*
	 * getSafeProperty, not a bare lookup: contextualId is read off the DOM
	 * (data-contextual-id, or a tile's data-value), so it is caller-supplied as far
	 * as this module is concerned. The helper rejects '__proto__' and friends and
	 * requires an own property, which is also why the id charset is [A-Za-z0-9_-] —
	 * the same characters it has to be to serve as the card's DOM id below.
	 */
	const contextualConfig = getSafeProperty( contextualGroupData, contextualId, null );
	const fields = contextualConfig ? contextualConfig.fields : null;

	// Nothing declared for this item — collapse rather than show an empty box.
	if ( ! fields || fields.length === 0 ) {
		collapse();
		return false;
	}

	// 2. Build, move, and fill.
	buildPanel();
	initEscapeGuard();

	placePanel( container, element );

	ensureCard( app, key, contextualId, contextualConfig );
	showOnly( app, key );

	openKey = key;
	openContainer = container;

	// 3. Reveal. Unhide first so the transition has two frames to run between.
	clearTimeout( collapseTimer );
	panel.hidden = false;

	// Re-check ownership inside the frame: the panel can be closed (Escape) before the
	// callback runs, and collapse() would have removed an `is-open` not yet added.
	requestAnimationFrame( () => {
		if ( openKey === key ) {
			panel.classList.add( 'is-open' );
		}
	} );

	return true;
};

/**
 * Press-to-open, press-again-to-close — the trigger's behavior.
 *
 * Split from showContextualPanel() because a trigger PRESS on the open item means close
 * it, while a SELECTION landing on it means the panel should move here.
 *
 * The container is part of the match, not just the key: item ids are not unique to a
 * picker, so a press can arrive for the open item from a DIFFERENT grid. Matching on the
 * key alone closed a panel the user could not even see, in the sub-tab they just left.
 *
 * @param {Object}      app             Main App instance.
 * @param {string}      contextualGroup Group slug.
 * @param {string}      contextualId    Item identifier.
 * @param {HTMLElement} element         The element the panel opens beneath.
 * @param {HTMLElement} container       Its parent container.
 * @returns {Promise<boolean>} True if the panel is now open.
 */
export const toggleContextualPanel = async (
	app,
	contextualGroup,
	contextualId,
	element,
	container,
) => {
	const isOpenHere = openKey &&
		openKey === `${contextualGroup}:${contextualId}` &&
		openContainer === container;

	if ( isOpenHere ) {
		collapse();
		return false;
	}

	return showContextualPanel( app, contextualGroup, contextualId, element, container );
};

/**
 * Whether any contextual panel is currently open.
 *
 * Lets a picker keep the panel pointed at the current selection: when the selection
 * changes while a panel is open, the panel should follow rather than strand itself on the
 * item that is no longer selected.
 *
 * @returns {boolean} True while a panel is open.
 */
export const isContextualPanelOpen = () => Boolean( openKey );

/**
 * Close the panel from outside the module.
 *
 * For the other half of "the panel follows the selection": a selection can land on an
 * item that has nothing to show, and then following it means closing. Cards are kept, as
 * with any other collapse, so returning to an edited item still finds its edits.
 */
export const closeContextualPanel = () => collapse();
