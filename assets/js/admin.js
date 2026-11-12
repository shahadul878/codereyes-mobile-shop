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

	// Receipts opened from the POS with &print=1 print straight away.
	if ( new URLSearchParams( window.location.search ).has( 'print' ) && document.querySelector( '.cmshop-receipt, .cmshop-invoice' ) ) {
		window.addEventListener( 'load', () => window.print() );
	}
}() );
