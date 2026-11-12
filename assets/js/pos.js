/**
 * Codereyes Mobile Shop — full-screen point of sale.
 *
 * Product grid with type and brand tabs, colour/storage picker for new
 * phones, scanner search, customer lookup, split payments with quick cash,
 * held sales and keyboard shortcuts (F2 search, F8 pay, F9 hold, Esc close).
 * The DOM is built with textContent only. Logic lives in pos-core.js.
 */
( function ( wp, config, core ) {
	'use strict';

	const root = document.getElementById( 'cmshop-pos' );

	if ( ! root || ! config || ! core ) {
		return;
	}

	const { __, _n, sprintf } = wp.i18n;
	const api = ( path, options = {} ) => wp.apiFetch( { path: '/' + config.namespace + path, ...options } );
	const find = ( selector ) => root.querySelector( selector );
	const money = ( value ) => core.money( value, config.symbol );
	const SEARCH_DELAY = 350;
	const PHONE_DIGITS = 11;
	const TYPES = [
		[ 'all', __( 'All', 'codereyes-mobile-shop' ) ],
		[ 'new', __( 'New phones', 'codereyes-mobile-shop' ) ],
		[ 'used', __( 'Used phones', 'codereyes-mobile-shop' ) ],
		[ 'accessory', __( 'Accessories', 'codereyes-mobile-shop' ) ],
	];
	const METHODS = Object.entries( config.methods || {} ).filter( ( [ key ] ) => 'online' !== key );

	const ui = {
		search: find( '#cmshop-pos-search' ),
		types: find( '[data-types]' ),
		brands: find( '[data-brands]' ),
		grid: find( '[data-grid]' ),
		gridEmpty: find( '[data-grid-empty]' ),
		phone: find( '#cmshop-pos-phone' ),
		name: find( '#cmshop-pos-name' ),
		customerInfo: find( '[data-customer-info]' ),
		lines: find( '[data-lines]' ),
		trades: find( '[data-trades]' ),
		cartEmpty: find( '[data-cart-empty]' ),
		discount: find( '[data-discount]' ),
		note: find( '[data-note]' ),
		payments: find( '[data-payments]' ),
		quick: find( '[data-quick]' ),
		balance: find( '[data-balance]' ),
		error: find( '[data-error]' ),
		heldCount: find( '[data-held-count]' ),
	};

	let state = {
		tiles: [],
		brands: [],
		filter: { type: 'all', brand: '', query: '' },
		lines: [],
		trades: [],
		payments: [ { method: 'cash', amount: '', reference: '' } ],
		held: [],
		busy: false,
	};

	const { el, icon } = window.cmshopPosDom;

	/**
	 * Show or clear the error line.
	 *
	 * @param {string} message Message ('' to clear).
	 */
	function showError( message ) {
		ui.error.textContent = message;
		ui.error.hidden = '' === message;
	}

	/**
	 * Message from a failed request.
	 *
	 * @param {Object} failure apiFetch rejection.
	 * @return {string} Message.
	 */
	function failureText( failure ) {
		return ( failure && ( failure.error || failure.message ) ) || __( 'Something went wrong. Please try again.', 'codereyes-mobile-shop' );
	}

	/* ---------- Grid ---------- */

	function renderFilters() {
		ui.types.replaceChildren( ...TYPES.map( ( [ key, label ] ) => el( 'button', { type: 'button', className: 'cmshop-pos-tab' + ( key === state.filter.type ? ' is-active' : '' ), 'aria-pressed': String( key === state.filter.type ), 'data-type': key, text: label } ) ) );
		ui.brands.replaceChildren(
			el( 'button', { type: 'button', className: 'cmshop-pos-tab' + ( '' === state.filter.brand ? ' is-active' : '' ), 'aria-pressed': String( '' === state.filter.brand ), 'data-brand': '', text: __( 'All brands', 'codereyes-mobile-shop' ) } ),
			...state.brands.map( ( brand ) => el( 'button', { type: 'button', className: 'cmshop-pos-tab' + ( brand === state.filter.brand ? ' is-active' : '' ), 'aria-pressed': String( brand === state.filter.brand ), 'data-brand': brand, text: brand } ) )
		);
	}

	function renderGrid() {
		const tiles = state.tiles.filter( ( tile ) => core.matches( tile, state.filter ) );

		ui.grid.replaceChildren( ...tiles.map( ( tile ) => window.cmshopPosDom.tile( tile, money ) ) );
		ui.gridEmpty.hidden = 0 < tiles.length;
		ui.gridEmpty.textContent = 0 === state.tiles.length ? __( 'Nothing in stock yet. Add phones and accessories under Stock.', 'codereyes-mobile-shop' ) : __( 'Nothing matches. Try another tab or search.', 'codereyes-mobile-shop' );
	}

	function loadCatalog() {
		return api( '/pos/catalog' ).then( ( response ) => {
			state = { ...state, tiles: response.data.tiles, brands: response.data.brands };
			renderFilters();
			renderGrid();
		} ).catch( ( failure ) => showError( failureText( failure ) ) );
	}

	/* ---------- Cart ---------- */

	/**
	 * Add a tile (or one phone of a model) to the sale.
	 *
	 * @param {Object}      tile Tile.
	 * @param {Object|null} unit Phone unit.
	 */
	function add( tile, unit ) {
		const result = core.addLine( state.lines, core.lineFor( tile, unit ) );

		showError( 'max' === result.error ? __( 'That is all the stock there is of this item.', 'codereyes-mobile-shop' ) : '' );
		state = { ...state, lines: result.lines };
		renderCart();
	}

	function renderCart() {
		ui.cartEmpty.hidden = 0 < state.lines.length;
		ui.lines.replaceChildren( ...state.lines.map( ( line ) => el( 'li', { className: 'cmshop-pos-line', 'data-line': line.key }, [
			el( 'div', { className: 'cmshop-pos-line__main' }, [
				el( 'strong', { text: line.title } ),
				line.detail ? el( 'span', { text: line.detail } ) : null,
				line.imei ? el( 'code', { text: 'IMEI ' + line.imei } ) : null,
			] ),
			el( 'div', { className: 'cmshop-pos-line__qty' }, 1 < line.max ? [
				el( 'button', { type: 'button', 'data-qty': '-1', 'aria-label': __( 'One less', 'codereyes-mobile-shop' ), text: '−' } ),
				el( 'span', { text: String( line.quantity ) } ),
				el( 'button', { type: 'button', 'data-qty': '1', 'aria-label': __( 'One more', 'codereyes-mobile-shop' ), text: '+' } ),
			] : [ el( 'span', { text: '1' } ) ] ),
			el( 'input', { type: 'text', inputmode: 'decimal', className: 'cmshop-pos-line__price', value: core.amount( line.price ), 'aria-label': __( 'Price', 'codereyes-mobile-shop' ), 'data-price': '' } ),
			el( 'span', { className: 'cmshop-pos-line__total', text: money( line.price * line.quantity ) } ),
			el( 'button', { type: 'button', className: 'cmshop-pos-x', 'data-remove': '', 'aria-label': __( 'Remove', 'codereyes-mobile-shop' ), text: '×' } ),
		] ) ) );
		renderTotals();
	}

	/**
	 * Trade-in value in cents.
	 *
	 * @return {number} Credit.
	 */
	function credit() {
		return state.trades.reduce( ( sum, trade ) => sum + core.cents( trade.value ), 0 );
	}

	function renderTrades() {
		ui.trades.replaceChildren( ...state.trades.map( ( trade, index ) => el( 'li', { className: 'cmshop-pos-line', 'data-trade': String( index ) }, [
			el( 'div', { className: 'cmshop-pos-line__main' }, [
				/* translators: %s: phone taken in trade. */
				el( 'strong', { text: sprintf( __( 'Trade-in: %s', 'codereyes-mobile-shop' ), [ trade.brand, trade.model, trade.storage ].filter( Boolean ).join( ' ' ) ) } ),
				el( 'code', { text: 'IMEI ' + trade.imei1 } ),
			] ),
			el( 'span', { className: 'cmshop-pos-line__total', text: '-' + money( core.cents( trade.value ) ) } ),
			el( 'button', { type: 'button', className: 'cmshop-pos-x', 'data-untrade': '', 'aria-label': __( 'Remove trade-in', 'codereyes-mobile-shop' ), text: '×' } ),
		] ) ) );
		renderTotals();
	}

	function renderTotals() {
		const sums = core.totals( state.lines, ui.discount.value );
		const trade = credit();
		const toPay = Math.max( 0, sums.total - trade );

		find( '[data-subtotal]' ).textContent = money( sums.subtotal );
		find( '[data-discount-row]' ).hidden = 0 === sums.discount;
		find( '[data-discount-text]' ).textContent = '-' + money( sums.discount );
		find( '[data-total]' ).textContent = money( sums.total );
		find( '[data-trade-row]' ).hidden = 0 === trade;
		find( '[data-trade-text]' ).textContent = '-' + money( trade );
		find( '[data-topay-row]' ).hidden = 0 === trade;
		find( '[data-topay]' ).textContent = money( toPay );
		ui.payments.querySelectorAll( '[data-amount]' ).forEach( ( input, index ) => {
			input.placeholder = 0 === index ? core.amount( toPay ) : '0';
		} );
		ui.quick.replaceChildren( ...core.quickCash( toPay ).map( ( value, index ) => el( 'button', { type: 'button', className: 'cmshop-pos-cash', 'data-cash': String( value ), text: 0 === index ? __( 'Exact', 'codereyes-mobile-shop' ) : money( value ) } ) ) );
		renderBalance();
	}

	function renderBalance() {
		const total = core.totals( state.lines, ui.discount.value ).total;
		const sums = core.balance( state.payments, total, credit() );
		let text = __( 'Paid in full', 'codereyes-mobile-shop' );
		let tone = 'ok';

		if ( 0 === state.lines.length ) {
			text = '';
		} else if ( 0 < sums.change ) {
			/* translators: %s: change to give back. */
			text = sprintf( __( 'Change to give: %s', 'codereyes-mobile-shop' ), money( sums.change ) );
			tone = 'change';
		} else if ( 0 < sums.due ) {
			/* translators: %s: amount still owed. */
			text = sprintf( __( 'Still owed: %s (goes on the customer\'s account)', 'codereyes-mobile-shop' ), money( sums.due ) );
			tone = 'due';
		}

		ui.balance.textContent = text;
		ui.balance.className = 'cmshop-pos-balance is-' + tone;
		renderPlan( sums.due );
	}

	/**
	 * Installment options, shown only while money is still owed.
	 *
	 * @param {number} due Amount owed in cents.
	 */
	function renderPlan( due ) {
		const plan = find( '[data-plan]' );
		const count = Number( find( '[data-plan-count]' ).value );

		plan.hidden = 0 >= due;
		find( '[data-plan-each]' ).textContent = 0 < count && 0 < due
			/* translators: 1: number of installments, 2: amount of each. */
			? sprintf( __( '%1$d payments of about %2$s', 'codereyes-mobile-shop' ), count, money( Math.floor( due / count ) ) )
			: '';
	}

	/* ---------- Payments ---------- */

	function renderPayments() {
		ui.payments.replaceChildren( ...state.payments.map( ( row, index ) => el( 'div', { className: 'cmshop-pos-payrow', 'data-pay': String( index ) }, [
			el( 'select', { 'aria-label': __( 'Payment method', 'codereyes-mobile-shop' ), 'data-method': '' }, METHODS.concat( 'store_credit' === row.method ? [ [ 'store_credit', __( 'Store credit', 'codereyes-mobile-shop' ) ] ] : [] ).map( ( [ key, label ] ) => {
				const option = el( 'option', { value: key, text: label } );
				option.selected = key === row.method;
				return option;
			} ) ),
			el( 'input', { type: 'text', inputmode: 'decimal', value: row.amount, 'aria-label': __( 'Amount', 'codereyes-mobile-shop' ), 'data-amount': '' } ),
			'cash' !== row.method && 'store_credit' !== row.method ? el( 'input', { type: 'text', value: row.reference, placeholder: __( 'Transaction ID', 'codereyes-mobile-shop' ), 'aria-label': __( 'Transaction ID', 'codereyes-mobile-shop' ), 'data-reference': '' } ) : null,
			0 < index ? el( 'button', { type: 'button', className: 'cmshop-pos-x', 'data-unpay': '', 'aria-label': __( 'Remove payment', 'codereyes-mobile-shop' ), text: '×' } ) : null,
		] ) ) );
		renderTotals();
	}

	/**
	 * Change one field of one payment row without redrawing the inputs.
	 *
	 * @param {number} index Row.
	 * @param {Object} patch Fields.
	 */
	function updatePayment( index, patch ) {
		state = { ...state, payments: state.payments.map( ( row, i ) => ( i === index ? { ...row, ...patch } : row ) ) };
	}

	/* ---------- Customer ---------- */

	let lookupTimer = 0;

	function lookupCustomer() {
		const phone = core.toAscii( ui.phone.value ).replace( /[^\d+]/g, '' );

		ui.customerInfo.hidden = true;

		if ( phone.replace( /\D/g, '' ).length < PHONE_DIGITS ) {
			return;
		}

		api( '/pos/customer?phone=' + encodeURIComponent( phone ) ).then( ( response ) => {
			const customer = response.data;

			ui.customerInfo.hidden = false;

			if ( ! customer ) {
				ui.customerInfo.textContent = __( 'New customer. Add a name to save them.', 'codereyes-mobile-shop' );
				ui.customerInfo.className = 'cmshop-pos-customer__info';
				return;
			}

			if ( '' === ui.name.value.trim() ) {
				ui.name.value = customer.name;
			}

			const parts = [
				/* translators: %d: number of purchases. */
				sprintf( _n( '%d purchase', '%d purchases', customer.sales, 'codereyes-mobile-shop' ), customer.sales ),
				/* translators: %s: total spent. */
				sprintf( __( 'spent %s', 'codereyes-mobile-shop' ), customer.spent_text ),
				/* translators: %s: date of last visit. */
				customer.last_text ? sprintf( __( 'last visit %s', 'codereyes-mobile-shop' ), customer.last_text ) : '',
			];
			const owes = 0 < core.cents( customer.due );

			/* translators: %s: amount the customer owes. */
			ui.customerInfo.textContent = parts.filter( Boolean ).join( ' · ' ) + ( owes ? ' · ' + sprintf( __( 'owes %s', 'codereyes-mobile-shop' ), customer.due_text ) : '' );
			ui.customerInfo.className = 'cmshop-pos-customer__info is-known' + ( owes ? ' owes' : '' );

			if ( 0 < core.cents( customer.credit || '' ) ) {
				/* translators: %s: store credit balance. */
				ui.customerInfo.append( ' ', el( 'button', { type: 'button', className: 'cmshop-pos-credit', 'data-use-credit': String( core.cents( customer.credit ) ), text: sprintf( __( 'Use store credit %s', 'codereyes-mobile-shop' ), customer.credit_text ) } ) );
			}
		} ).catch( () => {
			ui.customerInfo.hidden = true;
		} );
	}

	/* ---------- Dialogs ---------- */

	function dialog( name ) {
		return find( '[data-dialog="' + name + '"]' );
	}

	/**
	 * Colour/storage picker for a new-phone model.
	 *
	 * @param {Object} tile Model tile.
	 */
	function openVariant( tile ) {
		const box = dialog( 'variant' );
		const inCart = new Set( state.lines.map( ( line ) => line.itemId ) );
		const showUnits = ( option ) => {
			box.querySelectorAll( '[data-option]' ).forEach( ( button ) => button.classList.toggle( 'is-active', button.dataset.option === option.key ) );
			box.querySelector( '[data-units]' ).replaceChildren( ...option.units.filter( ( unit ) => ! inCart.has( unit.id ) ).map( ( unit ) => el( 'li', {}, [
				el( 'button', { type: 'button', className: 'cmshop-pos-unit', 'data-unit': String( unit.id ) }, [
					el( 'code', { text: unit.imei } ),
					el( 'strong', { text: money( core.cents( unit.price ) ) } ),
				] ),
			] ) ) );
			box.querySelector( '[data-units]' ).onclick = ( event ) => {
				const button = event.target.closest( '[data-unit]' );
				const unit = button && option.units.find( ( candidate ) => String( candidate.id ) === button.dataset.unit );

				if ( unit ) {
					add( tile, { ...unit, option } );
					box.close();
				}
			};
		};

		box.querySelector( '[data-variant-title]' ).textContent = tile.title;
		box.querySelector( '[data-options]' ).replaceChildren( ...tile.options.map( ( option ) => el( 'button', { type: 'button', className: 'cmshop-pos-option', 'data-option': option.key }, [
			el( 'i', { style: option.swatch ? 'background:' + option.swatch : null } ),
			el( 'span', { text: [ option.storage, option.color ].filter( Boolean ).join( ' · ' ) || __( 'Standard', 'codereyes-mobile-shop' ) } ),
			el( 'small', { text: String( option.units.filter( ( unit ) => ! inCart.has( unit.id ) ).length ) } ),
		] ) ) );
		box.querySelector( '[data-options]' ).onclick = ( event ) => {
			const button = event.target.closest( '[data-option]' );
			const option = button && tile.options.find( ( candidate ) => candidate.key === button.dataset.option );

			if ( option ) {
				showUnits( option );
			}
		};
		showUnits( tile.options[ 0 ] );
		box.showModal();
	}

	function refreshHeld() {
		return api( '/pos/held' ).then( ( response ) => {
			state = { ...state, held: response.data };
			ui.heldCount.textContent = String( response.data.length );
		} ).catch( () => {} );
	}

	function openHeld() {
		const box = dialog( 'held' );
		const list = box.querySelector( '[data-held]' );

		list.replaceChildren( ...( 0 === state.held.length ? [ el( 'li', { className: 'cmshop-pos-empty', text: __( 'No sales on hold.', 'codereyes-mobile-shop' ) } ) ] : state.held.map( ( entry ) => el( 'li', { className: 'cmshop-pos-heldrow' }, [
			el( 'div', {}, [
				el( 'strong', { text: entry.label } ),
				/* translators: 1: number of items, 2: time saved. */
				el( 'span', { text: sprintf( __( '%1$d items · held at %2$s', 'codereyes-mobile-shop' ), ( entry.cart.lines || [] ).length, String( entry.saved_at ).slice( 11, 16 ) ) } ),
			] ),
			el( 'button', { type: 'button', className: 'button button-primary', 'data-resume': entry.id, text: __( 'Resume', 'codereyes-mobile-shop' ) } ),
			el( 'button', { type: 'button', className: 'button', 'data-discard': entry.id, text: __( 'Remove', 'codereyes-mobile-shop' ) } ),
		] ) ) ) );
		box.showModal();
	}

	/* ---------- Sale actions ---------- */

	function reset() {
		state = { ...state, lines: [], trades: [], payments: [ { method: 'cash', amount: '', reference: '' } ] };
		[ ui.phone, ui.name, ui.discount, ui.note ].forEach( ( input ) => {
			input.value = '';
		} );
		find( '[data-plan-count]' ).value = '0';
		ui.customerInfo.hidden = true;
		showError( '' );
		renderPayments();
		renderTrades();
		renderCart();
		ui.search.focus();
	}

	/**
	 * Read and check the trade-in form, then add the phone as credit.
	 *
	 * @param {HTMLFormElement} form Trade-in form.
	 */
	function addTrade( form ) {
		const data = new window.FormData( form );
		const trade = {
			brand: String( data.get( 'brand' ) || '' ).trim(),
			model: String( data.get( 'model' ) || '' ).trim(),
			storage: String( data.get( 'storage' ) || '' ).trim(),
			color: String( data.get( 'color' ) || '' ).trim(),
			grade: String( data.get( 'grade' ) || 'good' ),
			battery_health: String( data.get( 'battery_health' ) || '' ).trim(),
			imei1: core.toAscii( data.get( 'imei1' ) || '' ).replace( /\D/g, '' ),
			value: String( data.get( 'value' ) || '' ).trim(),
			checks: data.getAll( 'checks' ).map( String ),
		};
		const error = form.querySelector( '[data-trade-error]' );
		let problem = '';

		if ( '' === trade.brand || '' === trade.model ) {
			problem = __( 'Enter the brand and model.', 'codereyes-mobile-shop' );
		} else if ( 15 !== trade.imei1.length ) {
			problem = __( 'Enter the 15-digit IMEI (dial *#06#).', 'codereyes-mobile-shop' );
		} else if ( 0 >= core.cents( trade.value ) ) {
			problem = __( 'Enter what you are giving for the phone.', 'codereyes-mobile-shop' );
		} else if ( state.trades.some( ( other ) => other.imei1 === trade.imei1 ) || 3 <= state.trades.length ) {
			problem = __( 'This phone is already added, or you have 3 trade-ins.', 'codereyes-mobile-shop' );
		}

		error.textContent = problem;
		error.hidden = '' === problem;

		if ( '' === problem ) {
			state = { ...state, trades: state.trades.concat( [ trade ] ) };
			form.reset();
			dialog( 'trade' ).close();
			renderTrades();
		}
	}

	function hold() {
		if ( 0 === state.lines.length || state.busy ) {
			showError( 0 === state.lines.length ? __( 'Add items before holding a sale.', 'codereyes-mobile-shop' ) : '' );
			return;
		}

		const label = ui.name.value.trim() || ui.phone.value.trim() || sprintf( /* translators: %s: time. */ __( 'Sale at %s', 'codereyes-mobile-shop' ), new Date().toTimeString().slice( 0, 5 ) );

		api( '/pos/held', { method: 'POST', data: { label, cart: { lines: state.lines, trades: state.trades, phone: ui.phone.value, name: ui.name.value, discount: ui.discount.value, note: ui.note.value } } } )
			.then( () => refreshHeld() )
			.then( reset )
			.catch( ( failure ) => showError( failureText( failure ) ) );
	}

	/**
	 * Load a held sale back into the cart, keeping only lines still in stock.
	 *
	 * @param {Object} entry Held entry.
	 */
	function resume( entry ) {
		const available = new Set( state.tiles.flatMap( ( tile ) => ( tile.options ? tile.options.flatMap( ( option ) => option.units.map( ( unit ) => unit.id ) ) : [ tile.id ] ) ) );
		const lines = ( entry.cart.lines || [] ).filter( ( line ) => available.has( line.itemId ) );

		api( '/pos/held/' + entry.id, { method: 'DELETE' } ).then( refreshHeld );
		reset();
		state = { ...state, lines, trades: Array.isArray( entry.cart.trades ) ? entry.cart.trades : [] };
		ui.phone.value = entry.cart.phone || '';
		ui.name.value = entry.cart.name || '';
		ui.discount.value = entry.cart.discount || '';
		ui.note.value = entry.cart.note || '';
		renderTrades();
		renderCart();
		lookupCustomer();
		showError( lines.length < ( entry.cart.lines || [] ).length ? __( 'Some items were sold meanwhile and were left out.', 'codereyes-mobile-shop' ) : '' );
	}

	function pay() {
		const total = core.totals( state.lines, ui.discount.value ).total;
		const sums = core.balance( state.payments, total, credit() );

		if ( state.busy || 0 === state.lines.length ) {
			showError( 0 === state.lines.length ? __( 'Add items to the sale first.', 'codereyes-mobile-shop' ) : '' );
			return;
		}

		if ( 0 < sums.due && '' === ui.phone.value.trim() ) {
			showError( __( 'Add the customer\'s phone number to sell on credit.', 'codereyes-mobile-shop' ) );
			ui.phone.focus();
			return;
		}

		const single = 1 === state.payments.length && '' === String( state.payments[ 0 ].amount ).trim();
		const payments = single ? [ { ...state.payments[ 0 ], amount: core.amount( sums.toPay ) } ] : state.payments;

		state = { ...state, busy: true };
		root.classList.add( 'is-busy' );
		showError( '' );

		api( '/pos/checkout', {
			method: 'POST',
			data: {
				lines: state.lines.map( ( line ) => ( { item_id: line.itemId, quantity: line.quantity, unit_price: core.amount( line.price ) } ) ),
				customer_name: ui.name.value,
				customer_phone: ui.phone.value,
				discount: ui.discount.value,
				notes: ui.note.value,
				payments,
				trade_ins: state.trades,
				installments: 0 < sums.due ? { count: Number( find( '[data-plan-count]' ).value ), interval: find( '[data-plan-interval]' ).value, first_date: find( '[data-plan-first]' ).value } : null,
			},
		} ).then( ( response ) => {
			const box = dialog( 'done' );
			const url = config.receipt + '&id=' + response.data.sale_id;

			box.querySelector( '[data-receipt="thermal"]' ).href = url + '&format=thermal&print=1';
			box.querySelector( '[data-receipt="a4"]' ).href = url + '&format=a4&print=1';
			box.querySelector( '[data-receipt="whatsapp"]' ).href = response.data.whatsapp;
			box.querySelector( '[data-done-text]' ).textContent = 0 < sums.change
				? sprintf( /* translators: %s: change. */ __( 'Give change: %s', 'codereyes-mobile-shop' ), money( sums.change ) )
				: ( 0 < sums.due ? sprintf( /* translators: %s: amount owed. */ __( '%s added to the customer\'s account', 'codereyes-mobile-shop' ), money( sums.due ) ) : __( 'Paid in full', 'codereyes-mobile-shop' ) );
			reset();
			box.showModal();
			loadCatalog();
		} ).catch( ( failure ) => showError( failureText( failure ) ) ).finally( () => {
			state = { ...state, busy: false };
			root.classList.remove( 'is-busy' );
		} );
	}

	/* ---------- Events ---------- */

	let searchTimer = 0;

	ui.search.addEventListener( 'input', () => {
		window.clearTimeout( searchTimer );
		searchTimer = window.setTimeout( () => {
			state = { ...state, filter: { ...state.filter, query: ui.search.value } };
			renderGrid();
		}, SEARCH_DELAY );
	} );

	ui.search.addEventListener( 'keydown', ( event ) => {
		if ( 'Enter' !== event.key ) {
			return;
		}

		event.preventDefault();

		const hit = core.findCode( state.tiles, ui.search.value );
		const shown = state.tiles.filter( ( tile ) => core.matches( tile, { ...state.filter, query: ui.search.value } ) );
		const target = hit || ( 1 === shown.length ? { tile: shown[ 0 ], unit: null } : null );

		if ( ! target ) {
			return;
		}

		if ( 'model' === target.tile.kind && ! target.unit ) {
			openVariant( target.tile );
		} else {
			add( target.tile, target.unit );
		}

		ui.search.value = '';
		state = { ...state, filter: { ...state.filter, query: '' } };
		renderGrid();
	} );

	root.addEventListener( 'click', ( event ) => {
		const target = event.target;
		const tile = target.closest( '[data-tile]' );
		const action = target.closest( '[data-action]' );
		const line = target.closest( '[data-line]' );

		if ( target.closest( '[data-type]' ) ) {
			state = { ...state, filter: { ...state.filter, type: target.closest( '[data-type]' ).dataset.type } };
			renderFilters();
			renderGrid();
		} else if ( target.closest( '[data-brand]' ) ) {
			state = { ...state, filter: { ...state.filter, brand: target.closest( '[data-brand]' ).dataset.brand } };
			renderFilters();
			renderGrid();
		} else if ( tile ) {
			const picked = state.tiles.find( ( candidate ) => candidate.key === tile.dataset.tile );

			if ( picked && 'model' === picked.kind ) {
				openVariant( picked );
			} else if ( picked ) {
				add( picked, null );
			}
		} else if ( line && target.closest( '[data-qty]' ) ) {
			const current = state.lines.find( ( candidate ) => candidate.key === line.dataset.line );
			state = { ...state, lines: core.setQuantity( state.lines, current.key, current.quantity + Number( target.closest( '[data-qty]' ).dataset.qty ) ) };
			renderCart();
		} else if ( line && target.closest( '[data-remove]' ) ) {
			state = { ...state, lines: state.lines.filter( ( candidate ) => candidate.key !== line.dataset.line ) };
			renderCart();
		} else if ( target.closest( '[data-cash]' ) ) {
			const index = Math.max( 0, state.payments.findIndex( ( row ) => 'cash' === row.method ) );
			updatePayment( index, { method: 'cash', amount: core.amount( Number( target.closest( '[data-cash]' ).dataset.cash ) ) } );
			renderPayments();
		} else if ( target.closest( '[data-use-credit]' ) ) {
			const total = core.totals( state.lines, ui.discount.value ).total;
			const use = Math.min( Number( target.closest( '[data-use-credit]' ).dataset.useCredit ), Math.max( 0, total - credit() ) );
			const others = state.payments.filter( ( row ) => 'store_credit' !== row.method && ( '' !== String( row.amount ).trim() || 'cash' !== row.method ) );

			state = { ...state, payments: [ { method: 'store_credit', amount: core.amount( use ), reference: '' } ].concat( others.length ? others : [ { method: 'cash', amount: core.amount( Math.max( 0, total - credit() - use ) ), reference: '' } ] ) };
			renderPayments();
		} else if ( target.closest( '[data-untrade]' ) ) {
			const index = Number( target.closest( '[data-trade]' ).dataset.trade );
			state = { ...state, trades: state.trades.filter( ( trade, i ) => i !== index ) };
			renderTrades();
		} else if ( target.closest( '[data-unpay]' ) ) {
			const index = Number( target.closest( '[data-pay]' ).dataset.pay );
			state = { ...state, payments: state.payments.filter( ( row, i ) => i !== index ) };
			renderPayments();
		} else if ( target.closest( '[data-resume]' ) ) {
			dialog( 'held' ).close();
			resume( state.held.find( ( entry ) => entry.id === target.closest( '[data-resume]' ).dataset.resume ) );
		} else if ( target.closest( '[data-discard]' ) ) {
			api( '/pos/held/' + target.closest( '[data-discard]' ).dataset.discard, { method: 'DELETE' } ).then( refreshHeld ).then( openHeld );
		} else if ( action ) {
			runAction( action.dataset.action, action );
		}
	} );

	/**
	 * Toolbar and dialog buttons.
	 *
	 * @param {string}      name   Action.
	 * @param {HTMLElement} button Button.
	 */
	function runAction( name, button ) {
		if ( 'close' === name ) {
			button.closest( 'dialog' ).close();
		} else if ( 'held-open' === name ) {
			refreshHeld().then( openHeld );
		} else if ( 'fullscreen' === name ) {
			if ( document.fullscreenElement ) {
				document.exitFullscreen();
			} else if ( document.documentElement.requestFullscreen ) {
				document.documentElement.requestFullscreen();
			}
		} else if ( 'split' === name ) {
			const used = new Set( state.payments.map( ( row ) => row.method ) );
			const next = METHODS.find( ( [ key ] ) => ! used.has( key ) );

			if ( next && state.payments.length < 5 ) {
				state = { ...state, payments: state.payments.concat( [ { method: next[ 0 ], amount: '', reference: '' } ] ) };
				renderPayments();
			}
		} else if ( 'trade-open' === name ) {
			find( '#cmshop-pos-brands' ).replaceChildren( ...state.brands.map( ( brand ) => el( 'option', { value: brand } ) ) );
			dialog( 'trade' ).showModal();
		} else if ( 'hold' === name ) {
			hold();
		} else if ( 'pay' === name ) {
			pay();
		} else if ( 'new-sale' === name ) {
			dialog( 'done' ).close();
			ui.search.focus();
		}
	}

	ui.payments.addEventListener( 'input', ( event ) => {
		const row = event.target.closest( '[data-pay]' );
		const index = row ? Number( row.dataset.pay ) : -1;

		if ( event.target.matches( '[data-amount]' ) ) {
			updatePayment( index, { amount: event.target.value } );
			renderBalance();
		} else if ( event.target.matches( '[data-reference]' ) ) {
			updatePayment( index, { reference: event.target.value } );
		}
	} );

	ui.payments.addEventListener( 'change', ( event ) => {
		if ( event.target.matches( '[data-method]' ) ) {
			updatePayment( Number( event.target.closest( '[data-pay]' ).dataset.pay ), { method: event.target.value } );
			renderPayments();
		}
	} );

	ui.lines.addEventListener( 'change', ( event ) => {
		if ( event.target.matches( '[data-price]' ) ) {
			const key = event.target.closest( '[data-line]' ).dataset.line;
			state = { ...state, lines: state.lines.map( ( line ) => ( line.key === key ? { ...line, price: core.cents( event.target.value ) } : line ) ) };
			renderCart();
		}
	} );

	ui.discount.addEventListener( 'input', renderTotals );
	find( '[data-trade-form]' ).addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		addTrade( event.target );
	} );
	find( '[data-plan]' ).addEventListener( 'change', renderBalance );
	ui.phone.addEventListener( 'input', () => {
		window.clearTimeout( lookupTimer );
		lookupTimer = window.setTimeout( lookupCustomer, SEARCH_DELAY );
	} );

	document.addEventListener( 'keydown', ( event ) => {
		const open = root.querySelector( 'dialog[open]' );

		if ( 'F2' === event.key ) {
			event.preventDefault();
			ui.search.focus();
			ui.search.select();
		} else if ( 'F8' === event.key && ! open ) {
			event.preventDefault();
			pay();
		} else if ( 'F9' === event.key && ! open ) {
			event.preventDefault();
			hold();
		} else if ( 'Enter' === event.key && open && 'done' === open.dataset.dialog && ! event.target.closest( 'a' ) ) {
			event.preventDefault();
			open.close();
			ui.search.focus();
		}
	} );

	/* ---------- Start ---------- */

	find( '[data-cashier]' ).textContent = config.cashier || '';
	renderFilters();
	renderPayments();
	renderCart();
	refreshHeld();
	loadCatalog().then( () => {
		const wanted = config.preselect && config.preselect.id;

		if ( wanted ) {
			const tile = state.tiles.find( ( candidate ) => candidate.id === wanted );
			const model = state.tiles.find( ( candidate ) => ( candidate.options || [] ).some( ( option ) => option.units.some( ( unit ) => unit.id === wanted ) ) );
			const option = model && model.options.find( ( candidate ) => candidate.units.some( ( unit ) => unit.id === wanted ) );

			if ( tile ) {
				add( tile, null );
			} else if ( option ) {
				add( model, { ...option.units.find( ( unit ) => unit.id === wanted ), option } );
			}
		}
	} );
	ui.search.focus();
}( window.wp, window.cmshopPos, window.cmshopPosCore ) );
