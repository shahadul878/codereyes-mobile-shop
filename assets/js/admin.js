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
}() );
