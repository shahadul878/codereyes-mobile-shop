/**
 * Codereyes Mobile Shop — point of sale.
 *
 * Totals shown here are a preview; the server re-prices every sale from stock.
 * All text is inserted with textContent, never innerHTML.
 */
( function () {
	'use strict';

	const config = window.cmshopPos || {};
	const { __, sprintf } = wp.i18n;
	const SEARCH_DELAY_MS = 250;
	const MIN_TERM = 2;

	const el = ( id ) => document.getElementById( id );
	const dom = {
		term: el( 'cmshop-pos-term' ),
		results: el( 'cmshop-pos-results' ),
		lines: el( 'cmshop-pos-lines' ),
		phone: el( 'cmshop-pos-phone' ),
		name: el( 'cmshop-pos-name' ),
		customers: el( 'cmshop-pos-customers' ),
		payment: el( 'cmshop-pos-payment' ),
		discount: el( 'cmshop-pos-discount' ),
		paid: el( 'cmshop-pos-paid' ),
		notes: el( 'cmshop-pos-notes' ),
		subtotal: el( 'cmshop-pos-subtotal' ),
		discountTotal: el( 'cmshop-pos-discount-total' ),
		total: el( 'cmshop-pos-total' ),
		balance: el( 'cmshop-pos-balance' ),
		balanceLabel: el( 'cmshop-pos-balance-label' ),
		error: el( 'cmshop-pos-error' ),
		complete: el( 'cmshop-pos-complete' ),
	};

	if ( ! dom.term ) {
		return;
	}

	/** @type {Array<{item: object, quantity: number, price: string}>} */
	let cart = [];
	let lastResults = [];
	let searchTimer = null;
	let customerTimer = null;
	let busy = false;

	/* ---------- money (integer cents, like the server) ---------- */

	const BENGALI = '০১২৩৪৫৬৭৮৯';

	function toAscii( value ) {
		return String( value ).replace( /[০-৯]/g, ( d ) => String( BENGALI.indexOf( d ) ) );
	}

	/** Parse "12,500.50" / "৳ ১৫০০" to cents, or null. Empty is null. */
	function parseCents( raw ) {
		const clean = toAscii( raw ).trim().replace( /^[^\d]+|[^\d]+$/g, '' ).replace( /,/g, '' );
		if ( '' === clean || ! /^\d+(\.\d{1,2})?$/.test( clean ) ) {
			return null;
		}
		const [ units, fraction = '' ] = clean.split( '.' );
		return parseInt( units, 10 ) * 100 + parseInt( ( fraction + '00' ).slice( 0, 2 ), 10 );
	}

	function formatCents( cents ) {
		const sign = cents < 0 ? '-' : '';
		const abs = Math.abs( cents );
		const units = Math.floor( abs / 100 ).toLocaleString( 'en-US' );
		return sign + ( config.symbol || '' ) + units + '.' + String( abs % 100 ).padStart( 2, '0' );
	}

	/* ---------- search ---------- */

	function search( term ) {
		return wp.apiFetch( {
			path: '/' + config.namespace + '/pos/items?search=' + encodeURIComponent( term ),
		} ).then( ( response ) => ( response && response.data ) || [] );
	}

	function renderResults( items ) {
		lastResults = items;
		dom.results.replaceChildren();

		if ( 0 === items.length ) {
			const li = document.createElement( 'li' );
			li.className = 'cmshop-pos__none';
			li.textContent = __( 'No items in stock match.', 'codereyes-mobile-shop' );
			dom.results.append( li );
			return;
		}

		items.forEach( ( item ) => {
			const li = document.createElement( 'li' );
			const button = document.createElement( 'button' );
			const name = document.createElement( 'strong' );
			const meta = document.createElement( 'span' );
			const price = document.createElement( 'span' );

			button.type = 'button';
			button.className = 'cmshop-pos__result';
			name.textContent = item.name;
			meta.className = 'cmshop-pos__meta';
			meta.textContent = 'phone' === item.type
				? item.imei + ' · ' + item.grade + ( 'reserved' === item.status ? ' · ' + __( 'Reserved', 'codereyes-mobile-shop' ) : '' )
				: /* translators: %d: quantity in stock. */ sprintf( __( '%d in stock', 'codereyes-mobile-shop' ), item.quantity ) + ( item.sku ? ' · ' + item.sku : '' );
			price.className = 'cmshop-pos__price';
			price.textContent = item.price_text;

			button.append( name, meta, price );
			button.addEventListener( 'click', () => addItem( item ) );
			li.append( button );
			dom.results.append( li );
		} );
	}

	dom.term.addEventListener( 'input', () => {
		clearTimeout( searchTimer );
		const term = dom.term.value.trim();

		if ( term.length < MIN_TERM ) {
			dom.results.replaceChildren();
			lastResults = [];
			return;
		}

		searchTimer = setTimeout( () => {
			search( term ).then( renderResults ).catch( showError );
		}, SEARCH_DELAY_MS );
	} );

	// Barcode scanners type the code and press Enter.
	dom.term.addEventListener( 'keydown', ( event ) => {
		if ( 'Enter' !== event.key ) {
			return;
		}
		event.preventDefault();
		clearTimeout( searchTimer );

		const term = dom.term.value.trim();
		if ( term.length < MIN_TERM ) {
			return;
		}

		search( term ).then( ( items ) => {
			if ( 1 === items.length ) {
				addItem( items[ 0 ] );
			} else {
				renderResults( items );
			}
		} ).catch( showError );
	} );

	/* ---------- cart ---------- */

	function addItem( item ) {
		const existing = cart.find( ( line ) => line.item.id === item.id );

		if ( existing ) {
			if ( 'phone' === item.type || existing.quantity >= item.quantity ) {
				showError( { message: sprintf( /* translators: %s: item name. */ __( '%s is already in the cart.', 'codereyes-mobile-shop' ), item.name ) } );
				return;
			}
			cart = cart.map( ( line ) => ( line === existing ? Object.assign( {}, line, { quantity: line.quantity + 1 } ) : line ) );
		} else {
			cart = cart.concat( [ { item, quantity: 1, price: formatPlain( parseCents( item.sale_price ) || 0 ) } ] );
		}

		dom.term.value = '';
		dom.results.replaceChildren();
		hideError();
		render();
		dom.term.focus();
	}

	function formatPlain( cents ) {
		return Math.floor( cents / 100 ) + '.' + String( cents % 100 ).padStart( 2, '0' );
	}

	function removeLine( id ) {
		cart = cart.filter( ( line ) => line.item.id !== id );
		render();
	}

	function updateLine( id, changes ) {
		cart = cart.map( ( line ) => ( line.item.id === id ? Object.assign( {}, line, changes ) : line ) );
		renderTotals();
	}

	function lineRow( line ) {
		const tr = document.createElement( 'tr' );
		const nameCell = document.createElement( 'td' );
		const qtyCell = document.createElement( 'td' );
		const priceCell = document.createElement( 'td' );
		const totalCell = document.createElement( 'td' );
		const removeCell = document.createElement( 'td' );

		const title = document.createElement( 'strong' );
		title.textContent = line.item.name;
		const sub = document.createElement( 'small' );
		sub.textContent = 'phone' === line.item.type ? 'IMEI ' + line.item.imei : line.item.sku;
		nameCell.append( title, document.createElement( 'br' ), sub );

		qtyCell.className = 'num';
		if ( 'phone' === line.item.type ) {
			qtyCell.textContent = '1';
		} else {
			const qty = document.createElement( 'input' );
			qty.type = 'number';
			qty.min = '1';
			qty.max = String( line.item.quantity );
			qty.value = String( line.quantity );
			qty.className = 'small-text';
			qty.setAttribute( 'aria-label', __( 'Quantity', 'codereyes-mobile-shop' ) );
			qty.addEventListener( 'input', () => {
				const value = Math.max( 1, Math.min( line.item.quantity, parseInt( qty.value, 10 ) || 1 ) );
				updateLine( line.item.id, { quantity: value } );
			} );
			qtyCell.append( qty );
		}

		priceCell.className = 'num';
		const price = document.createElement( 'input' );
		price.type = 'text';
		price.inputMode = 'decimal';
		price.value = line.price;
		price.className = 'cmshop-pos__price-input';
		price.setAttribute( 'aria-label', __( 'Unit price', 'codereyes-mobile-shop' ) );
		price.addEventListener( 'input', () => updateLine( line.item.id, { price: price.value } ) );
		priceCell.append( price );

		totalCell.className = 'num cmshop-pos__line-total';
		totalCell.dataset.id = String( line.item.id );

		const remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'button-link cmshop-pos__remove';
		remove.textContent = '×';
		remove.setAttribute( 'aria-label', sprintf( /* translators: %s: item name. */ __( 'Remove %s', 'codereyes-mobile-shop' ), line.item.name ) );
		remove.addEventListener( 'click', () => removeLine( line.item.id ) );
		removeCell.append( remove );

		tr.append( nameCell, qtyCell, priceCell, totalCell, removeCell );
		return tr;
	}

	function render() {
		dom.lines.replaceChildren();

		if ( 0 === cart.length ) {
			const tr = document.createElement( 'tr' );
			const td = document.createElement( 'td' );
			tr.className = 'cmshop-pos__empty';
			td.colSpan = 5;
			td.textContent = __( 'The cart is empty. Scan or search for an item.', 'codereyes-mobile-shop' );
			tr.append( td );
			dom.lines.append( tr );
		} else {
			cart.forEach( ( line ) => dom.lines.append( lineRow( line ) ) );
		}

		renderTotals();
	}

	/** Preview totals; returns null when any amount is invalid. */
	function totals() {
		let subtotal = 0;

		for ( const line of cart ) {
			const cents = parseCents( line.price );
			if ( null === cents ) {
				return null;
			}
			subtotal += cents * line.quantity;
		}

		const discount = '' === dom.discount.value.trim() ? 0 : parseCents( dom.discount.value );
		if ( null === discount || discount > subtotal ) {
			return null;
		}

		const total = subtotal - discount;
		const paid = '' === dom.paid.value.trim() ? total : parseCents( dom.paid.value );
		if ( null === paid ) {
			return null;
		}

		return { subtotal, discount, total, paid };
	}

	function renderTotals() {
		dom.lines.querySelectorAll( '.cmshop-pos__line-total' ).forEach( ( cell ) => {
			const line = cart.find( ( l ) => String( l.item.id ) === cell.dataset.id );
			const cents = line ? parseCents( line.price ) : null;
			cell.textContent = null === cents ? '—' : formatCents( cents * line.quantity );
		} );

		const t = totals();
		dom.complete.disabled = busy || 0 === cart.length || null === t;

		if ( null === t ) {
			[ dom.subtotal, dom.discountTotal, dom.total, dom.balance ].forEach( ( node ) => ( node.textContent = '—' ) );
			return;
		}

		dom.subtotal.textContent = formatCents( t.subtotal );
		dom.discountTotal.textContent = formatCents( t.discount );
		dom.total.textContent = formatCents( t.total );

		const due = t.total - t.paid;
		dom.balanceLabel.textContent = due > 0 ? __( 'Balance due', 'codereyes-mobile-shop' ) : __( 'Change', 'codereyes-mobile-shop' );
		dom.balance.textContent = formatCents( Math.abs( due ) );
		dom.balance.classList.toggle( 'is-due', due > 0 );
	}

	[ dom.discount, dom.paid ].forEach( ( input ) => input.addEventListener( 'input', renderTotals ) );

	/* ---------- customers ---------- */

	dom.phone.addEventListener( 'input', () => {
		clearTimeout( customerTimer );
		const term = dom.phone.value.trim();

		if ( term.length < 3 ) {
			return;
		}

		customerTimer = setTimeout( () => {
			wp.apiFetch( { path: '/' + config.namespace + '/pos/customers?search=' + encodeURIComponent( term ) } )
				.then( ( response ) => {
					const customers = ( response && response.data ) || [];
					dom.customers.replaceChildren();
					customers.forEach( ( customer ) => {
						const option = document.createElement( 'option' );
						option.value = customer.phone;
						option.label = customer.name;
						dom.customers.append( option );
					} );

					const exact = customers.find( ( c ) => c.phone === term );
					if ( exact && '' === dom.name.value.trim() ) {
						dom.name.value = exact.name;
					}
				} )
				.catch( () => {} );
		}, SEARCH_DELAY_MS );
	} );

	/* ---------- checkout ---------- */

	function showError( error ) {
		dom.error.textContent = ( error && error.message ) || __( 'Something went wrong. Please try again.', 'codereyes-mobile-shop' );
		dom.error.hidden = false;
	}

	function hideError() {
		dom.error.hidden = true;
		dom.error.textContent = '';
	}

	dom.complete.addEventListener( 'click', () => {
		if ( busy || null === totals() ) {
			return;
		}

		busy = true;
		hideError();
		dom.complete.disabled = true;
		dom.complete.textContent = __( 'Saving…', 'codereyes-mobile-shop' );

		wp.apiFetch( {
			path: '/' + config.namespace + '/pos/checkout',
			method: 'POST',
			data: {
				lines: cart.map( ( line ) => ( { item_id: line.item.id, quantity: line.quantity, unit_price: line.price } ) ),
				customer_name: dom.name.value,
				customer_phone: dom.phone.value,
				payment_method: dom.payment.value,
				discount: dom.discount.value,
				amount_paid: dom.paid.value,
				notes: dom.notes.value,
			},
		} )
			.then( ( response ) => {
				window.location.assign( response.data.receipt_url );
			} )
			.catch( ( error ) => {
				busy = false;
				dom.complete.textContent = __( 'Complete sale', 'codereyes-mobile-shop' );
				showError( error && error.error ? { message: error.error } : error );
				renderTotals();
			} );
	} );

	if ( config.preselect ) {
		addItem( config.preselect );
	}

	render();
}() );
