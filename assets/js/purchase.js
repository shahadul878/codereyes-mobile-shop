/**
 * New purchase form: add phone/accessory lines, check scanned IMEIs as they
 * arrive (format, check digit, repeats across lines) and keep totals live.
 * The server checks everything again; this only gives early feedback.
 */
( function ( window, document ) {
	'use strict';

	// Found through the lines container: other plugins' notices may add their own forms to the page.
	const lines = document.querySelector( '.cmshop-purchase [data-lines]' );
	const form = lines ? lines.closest( 'form' ) : null;

	if ( ! form ) {
		return;
	}

	const { __, _n, sprintf } = window.wp.i18n;
	const config = window.cmshopPurchase || { symbol: '' };
	const symbol = String( config.symbol || '' ).trim();
	const IMEI = /^\d{15}$/;
	const SEPARATORS = /[\r\n,;]+/;
	const BANGLA_ZERO = 0x09e6;

	/* ---------- Pure helpers ---------- */

	const toAscii = ( value ) => String( value ).replace( /[০-৯]/g, ( digit ) => String( digit.charCodeAt( 0 ) - BANGLA_ZERO ) );

	const cents = ( value ) => {
		const clean = toAscii( value ).replace( /[^\d.]/g, '' );
		const number = Number.parseFloat( clean );

		return Number.isFinite( number ) && 0 <= number ? Math.round( number * 100 ) : 0;
	};

	const money = ( value ) => ( symbol ? symbol + '\u00a0' : '' ) + ( value / 100 ).toLocaleString( 'en', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );

	const luhn = ( digits ) => {
		let sum = 0;

		for ( let i = 0; i < digits.length; i++ ) {
			let n = Number( digits[ digits.length - 1 - i ] );

			if ( 1 === i % 2 ) {
				n *= 2;
				n = 9 < n ? n - 9 : n;
			}

			sum += n;
		}

		return 0 === sum % 10;
	};

	const imeisOf = ( text ) => toAscii( text ).split( SEPARATORS ).map( ( part ) => part.replace( /[\s\-/.]+/g, '' ) ).filter( Boolean );

	/* ---------- Lines ---------- */

	let next = lines.querySelectorAll( '[data-line]' ).length;

	function addLine( type ) {
		const template = document.getElementById( 'cmshop-pline-' + type );
		const holder = document.createElement( 'div' );

		holder.innerHTML = template.innerHTML.replace( /__i__/g, String( next ) );
		next++;

		const line = holder.firstElementChild;

		lines.appendChild( line );
		toggleRestock( line );
		( line.querySelector( '[data-field="brand"], [data-restock]' ) || line ).focus();
		update();
	}

	function toggleRestock( line ) {
		const select = line.querySelector( '[data-restock]' );

		if ( ! select ) {
			return;
		}

		const isNew = '0' === select.value;

		line.querySelectorAll( '[data-field="name"], [data-field="sku"]' ).forEach( ( input ) => {
			input.closest( '.cmshop-field' ).hidden = ! isNew;
		} );
	}

	function fillRestockPrices( select ) {
		const option = select.selectedOptions[ 0 ];
		const line = select.closest( '[data-line]' );

		[ [ 'cost_price', 'cost' ], [ 'sale_price', 'price' ] ].forEach( ( [ field, data ] ) => {
			const input = line.querySelector( '[data-field="' + field + '"]' );

			if ( input && '' === input.value && option.dataset[ data ] ) {
				input.value = option.dataset[ data ];
			}
		} );
	}

	/* ---------- Live totals ---------- */

	function update() {
		const seen = new Map();
		let units = 0;
		let total = 0;

		lines.querySelectorAll( '[data-line]' ).forEach( ( line ) => {
			const cost = cents( ( line.querySelector( '[data-field="cost_price"]' ) || {} ).value || '' );
			let count = 0;

			if ( 'phone' === line.dataset.type ) {
				const box = line.querySelector( '[data-imeis]' );
				const bad = [];
				const twice = [];

				imeisOf( box.value ).forEach( ( imei ) => {
					if ( ! IMEI.test( imei ) || ! luhn( imei ) ) {
						bad.push( imei );
					} else if ( seen.has( imei ) ) {
						twice.push( imei );
					} else {
						seen.set( imei, true );
						count++;
					}
				} );

				const note = line.querySelector( '[data-imei-count]' );
				const parts = [ sprintf( /* translators: %d: phones scanned. */ _n( '%d phone', '%d phones', count, 'codereyes-mobile-shop' ), count ) ];

				if ( bad.length ) {
					parts.push( sprintf( /* translators: %s: IMEIs. */ __( 'not valid: %s', 'codereyes-mobile-shop' ), bad.join( ', ' ) ) );
				}

				if ( twice.length ) {
					parts.push( sprintf( /* translators: %s: IMEIs. */ __( 'scanned twice: %s', 'codereyes-mobile-shop' ), twice.join( ', ' ) ) );
				}

				note.textContent = parts.join( ' · ' );
				note.classList.toggle( 'is-bad', 0 < bad.length + twice.length );
				box.setAttribute( 'aria-invalid', 0 < bad.length + twice.length ? 'true' : 'false' );
			} else {
				const quantity = Number.parseInt( toAscii( ( line.querySelector( '[data-field="quantity"]' ) || {} ).value || '' ), 10 );
				count = Number.isFinite( quantity ) && 0 < quantity ? quantity : 0;
			}

			units += count;
			total += count * cost;
			line.querySelector( '[data-line-total]' ).textContent = count ? sprintf( '%d × %s = %s', count, money( cost ), money( count * cost ) ) : '';
		} );

		form.querySelector( '[data-units]' ).textContent = String( units );
		form.querySelector( '[data-total]' ).textContent = money( total );
		form.dataset.total = String( total );

		const left = total - cents( form.querySelector( '[name="paid"]' ).value );

		form.querySelector( '[data-owe]' ).textContent = 0 < left && 0 < total
			? sprintf( /* translators: %s: amount owed. */ __( 'You will owe the supplier %s', 'codereyes-mobile-shop' ), money( left ) )
			: '';
	}

	/* ---------- Events ---------- */

	form.addEventListener( 'input', update );

	form.addEventListener( 'change', ( event ) => {
		if ( event.target.matches( '[data-restock]' ) ) {
			toggleRestock( event.target.closest( '[data-line]' ) );
			fillRestockPrices( event.target );
			update();
		}

		if ( event.target.matches( '[name="supplier_id"]' ) ) {
			form.querySelector( '[data-new-supplier]' ).hidden = '0' !== event.target.value;
		}
	} );

	form.addEventListener( 'click', ( event ) => {
		const add = event.target.closest( '[data-add-line]' );
		const remove = event.target.closest( '[data-remove-line]' );
		const pay = event.target.closest( '[data-pay]' );

		if ( add ) {
			addLine( add.dataset.addLine );
		} else if ( remove ) {
			remove.closest( '[data-line]' ).remove();
			update();
		} else if ( pay ) {
			form.querySelector( '[name="paid"]' ).value = 'all' === pay.dataset.pay ? ( Number( form.dataset.total || 0 ) / 100 ).toFixed( 2 ) : '0';
			update();
		}
	} );

	// Barcode scanners press Enter after each code: a new line in the IMEI box, never a submit.
	form.addEventListener( 'keydown', ( event ) => {
		if ( 'Enter' === event.key && 'INPUT' === event.target.tagName && event.target.closest( '[data-line]' ) ) {
			event.preventDefault();
		}
	} );

	form.querySelector( '[data-new-supplier]' ).hidden = '0' !== form.querySelector( '[name="supplier_id"]' ).value;
	lines.querySelectorAll( '[data-line]' ).forEach( toggleRestock );
	update();
}( window, document ) );
