/**
 * Follow the redirect requested by a "Redirect the user" action after a Contact Form 7 submission.
 */
( function () {
	'use strict';

	document.addEventListener( 'wpcf7submit', ( event ) => {
		const response = event.detail && event.detail.apiResponse;
		if ( response && typeof response.glocalsaino_aat_redirect === 'string' && response.glocalsaino_aat_redirect ) {
			window.location.href = response.glocalsaino_aat_redirect;
		}
	} );
} )();
