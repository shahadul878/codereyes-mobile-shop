/**
 * Codereyes Mobile Shop — small admin behaviours: confirm links, print buttons.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		const confirmLink = event.target.closest( '.cmshop-confirm' );

		if ( confirmLink && ! window.confirm( confirmLink.dataset.confirm || '' ) ) {
			event.preventDefault();
			return;
		}

		if ( event.target.closest( '.cmshop-print' ) ) {
			event.preventDefault();
			window.print();
		}
	} );

	// Cash register: add up the note count as it is typed and compare with what should be there.
	const counter = document.querySelector( '.cmshop-count' );

	if ( counter ) {
		const symbol = ( document.querySelector( '[data-count-total]' ).textContent.match( /^[^\d-]+/ ) || [ '' ] )[ 0 ];
		const format = ( cents ) => symbol + ( Math.abs( cents ) / 100 ).toLocaleString( 'en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );

		counter.addEventListener( 'input', () => {
			const total = Array.from( counter.querySelectorAll( '[data-note]' ) ).reduce( ( sum, input ) => sum + ( Math.max( 0, parseInt( input.value || '0', 10 ) || 0 ) * Number( input.dataset.note ) * 100 ), 0 );
			const diff = total - Number( counter.dataset.expected );
			const out = counter.querySelector( '[data-count-diff]' );

			counter.querySelector( '[data-count-total]' ).textContent = format( total );
			out.className = 0 === diff ? 'is-even' : ( diff < 0 ? 'is-short' : 'is-over' );
			out.textContent = 0 === diff ? '✓' : ( diff < 0 ? '−' : '+' ) + format( diff );
		} );
	}

	// Receipts opened from the POS with &print=1 print straight away.
	if ( new URLSearchParams( window.location.search ).has( 'print' ) && document.querySelector( '.cmshop-receipt, .cmshop-invoice' ) ) {
		window.addEventListener( 'load', () => window.print() );
	}
}() );
