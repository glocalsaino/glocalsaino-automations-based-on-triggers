/**
 * Shared admin behaviour: confirmation for destructive links and buttons.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', ( e ) => {
		const target = e.target.closest( '.glocalsaino-aat-confirm' );
		if ( target && ! window.confirm( target.getAttribute( 'data-confirm' ) ) ) {
			e.preventDefault();
		}
	} );
} )();
