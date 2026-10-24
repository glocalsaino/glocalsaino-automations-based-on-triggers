/**
 * Embeddable widget (runs inside the iframe served by this site).
 */
( function () {
	'use strict';

	const cfg = window.glocalsainoAatWidget;
	const root = document.getElementById( 'gsaat-widget' );
	if ( ! cfg || ! root ) {
		return;
	}

	const status = root.querySelector( '.gsaat-w__status' );
	const form = root.querySelector( 'form' );
	const trigger = root.querySelector( '[data-gsaat-trigger]' );
	const button = form ? form.querySelector( 'button[type="submit"]' ) : trigger;
	const embedded = window.parent !== window;
	const parentUrl = new URLSearchParams( window.location.search ).get( 'parent' ) || ( embedded ? document.referrer : '' ) || '';

	let token = null;
	let captchaId = null;
	let captchaResponse = '';
	let busy = false;

	/* -------------------------------------------------------------- */
	/* Messages to the page that embeds the widget (loader script)    */
	/* -------------------------------------------------------------- */

	function post( type, data ) {
		if ( embedded ) {
			window.parent.postMessage( Object.assign( { type: 'glocalsaino-aat:' + type }, data ), '*' );
		}
	}

	function reportHeight() {
		post( 'height', { height: Math.ceil( document.documentElement.scrollHeight ) } );
	}

	if ( 'ResizeObserver' in window ) {
		new window.ResizeObserver( reportHeight ).observe( document.body );
	}
	window.addEventListener( 'load', reportHeight );
	reportHeight();

	/* -------------------------------------------------------------- */
	/* UI helpers                                                      */
	/* -------------------------------------------------------------- */

	function setStatus( text, kind ) {
		if ( ! status ) {
			return;
		}
		status.textContent = text || '';
		status.className = 'gsaat-w__status' + ( kind ? ' is-' + kind : '' );
		reportHeight();
	}

	function setBusy( value ) {
		busy = value;
		if ( button && button.tagName === 'BUTTON' ) {
			button.disabled = value;
		}
		if ( button ) {
			button.setAttribute( 'aria-busy', value ? 'true' : 'false' );
		}
	}

	function clearErrors() {
		root.querySelectorAll( '.gsaat-w__error' ).forEach( ( node ) => node.remove() );
		root.querySelectorAll( '[aria-invalid]' ).forEach( ( node ) => node.removeAttribute( 'aria-invalid' ) );
	}

	function showErrors( errors ) {
		let first = null;
		Object.keys( errors ).forEach( ( name ) => {
			const input = form ? form.elements.namedItem( name ) : null;
			if ( ! input || ! input.closest ) {
				return;
			}
			input.setAttribute( 'aria-invalid', 'true' );
			const message = document.createElement( 'span' );
			message.className = 'gsaat-w__error';
			message.id = 'gsaat-e-' + name;
			message.textContent = errors[ name ];
			input.setAttribute( 'aria-describedby', message.id );
			input.closest( '.gsaat-w__field' ).appendChild( message );
			first = first || input;
		} );
		if ( first ) {
			first.focus();
		}
		reportHeight();
	}

	function collectFields() {
		const fields = {};
		if ( ! form ) {
			return fields;
		}
		Array.from( form.elements ).forEach( ( element ) => {
			if ( ! element.name || element.name === 'gsaat_hp' ) {
				return;
			}
			fields[ element.name ] = element.type === 'checkbox' ? ( element.checked ? 'yes' : '' ) : element.value;
		} );
		return fields;
	}

	function honeypot() {
		const field = root.querySelector( 'input[name="gsaat_hp"]' );
		return field ? field.value : '';
	}

	/* -------------------------------------------------------------- */
	/* Captcha (optional)                                              */
	/* -------------------------------------------------------------- */

	function captchaApi() {
		if ( ! cfg.captcha ) {
			return null;
		}
		return cfg.captcha.provider === 'turnstile' ? window.turnstile : window.hcaptcha;
	}

	function initCaptcha() {
		const box = root.querySelector( '.gsaat-w__captcha' );
		const api = captchaApi();
		if ( ! box || ! api ) {
			return;
		}
		captchaId = api.render( box, {
			sitekey: cfg.captcha.siteKey,
			callback: ( response ) => {
				captchaResponse = response;
			},
			'expired-callback': () => {
				captchaResponse = '';
			},
		} );
		reportHeight();
	}

	function resetCaptcha() {
		const api = captchaApi();
		if ( api && captchaId !== null ) {
			try {
				api.reset( captchaId );
			} catch ( e ) {}
		}
		captchaResponse = '';
	}

	/* -------------------------------------------------------------- */
	/* Server                                                          */
	/* -------------------------------------------------------------- */

	function request( url, options ) {
		return window.fetch( url, Object.assign( { credentials: 'omit' }, options ) ).then( ( response ) =>
			response.json().then( ( json ) => ( { ok: response.ok, json } ), () => ( { ok: false, json: {} } ) )
		);
	}

	function getToken() {
		return request( cfg.tokenUrl + '?parent=' + encodeURIComponent( parentUrl ) ).then( ( { ok, json } ) => {
			if ( ! ok || ! json.token ) {
				throw new Error( json.message || cfg.i18n.error );
			}
			token = json.token;
		} );
	}

	function navigate( url ) {
		if ( ! embedded ) {
			window.location.href = url;
			return;
		}
		try {
			window.top.location.href = url;
		} catch ( e ) {}
		post( 'redirect', { url } );
		// If the browser blocked both, offer a link (a click always works).
		window.setTimeout( () => {
			const link = document.createElement( 'a' );
			link.href = url;
			link.target = '_top';
			link.className = 'gsaat-w__continue';
			link.textContent = cfg.i18n.continue;
			setStatus( '' );
			status.appendChild( link );
			reportHeight();
		}, 1500 );
	}

	function submit() {
		if ( busy ) {
			return;
		}
		if ( ! token ) {
			setStatus( cfg.i18n.error, 'error' );
			return;
		}
		if ( cfg.captcha && captchaApi() && ! captchaResponse ) {
			setStatus( cfg.i18n.captcha, 'error' );
			return;
		}
		clearErrors();
		setBusy( true );
		setStatus( cfg.i18n.sending );

		request( cfg.submitUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( {
				token,
				fields: collectFields(),
				hp: honeypot(),
				captcha: captchaResponse,
				page: parentUrl,
			} ),
		} )
			.then( ( { ok, json } ) => {
				token = null;
				resetCaptcha();
				if ( ok && json.success ) {
					if ( form ) {
						form.reset();
					}
					if ( json.redirect ) {
						setStatus( cfg.i18n.sending );
						navigate( json.redirect );
					} else {
						setStatus( json.message || '', 'success' );
					}
				} else {
					if ( json.data && json.data.fields ) {
						showErrors( json.data.fields );
					}
					setStatus( json.message || cfg.i18n.error, 'error' );
				}
				// Tokens are single-use: get a new one for the next submission.
				return getToken();
			} )
			.catch( ( error ) => setStatus( ( error && error.message ) || cfg.i18n.error, 'error' ) )
			.then( () => setBusy( false ) );
	}

	/* -------------------------------------------------------------- */
	/* Wiring                                                          */
	/* -------------------------------------------------------------- */

	if ( form ) {
		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			submit();
		} );
	}
	if ( trigger ) {
		trigger.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			submit();
		} );
	}

	setBusy( true );
	getToken()
		.then( () => {
			setBusy( false );
			initCaptcha();
		} )
		.catch( ( error ) => setStatus( ( error && error.message ) || cfg.i18n.error, 'error' ) );
} )();
