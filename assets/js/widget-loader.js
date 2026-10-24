/**
 * GlocalSaino Automations – widget loader, pasted on third-party websites:
 *
 *   <script src=".../widget-loader.js" data-glocalsaino-aat-widget="WIDGET URL" async></script>
 *
 * It inserts the widget iframe right after the script tag, adjusts its height to the
 * content and follows redirects requested by the widget. Messages are only accepted
 * from the widget's own site and iframe.
 */
( function () {
	'use strict';

	function mount( script ) {
		const src = script.getAttribute( 'data-glocalsaino-aat-widget' );
		let widgetUrl;
		try {
			widgetUrl = new URL( src, document.baseURI );
		} catch ( e ) {
			return;
		}
		if ( widgetUrl.protocol !== 'https:' && widgetUrl.protocol !== 'http:' ) {
			return;
		}
		script.setAttribute( 'data-glocalsaino-aat-mounted', '1' );
		widgetUrl.searchParams.set( 'parent', window.location.href.split( '#' )[ 0 ] );

		const iframe = document.createElement( 'iframe' );
		iframe.src = widgetUrl.toString();
		iframe.title = script.getAttribute( 'data-title' ) || 'Form';
		iframe.loading = 'lazy';
		iframe.style.width = '100%';
		iframe.style.border = '0';
		iframe.style.display = 'block';
		iframe.style.height = ( parseInt( script.getAttribute( 'data-height' ), 10 ) || 120 ) + 'px';
		script.parentNode.insertBefore( iframe, script.nextSibling );

		window.addEventListener( 'message', ( event ) => {
			if ( event.origin !== widgetUrl.origin || event.source !== iframe.contentWindow ) {
				return;
			}
			const data = event.data || {};
			if ( data.type === 'glocalsaino-aat:height' && typeof data.height === 'number' ) {
				iframe.style.height = Math.max( 40, Math.min( data.height, 5000 ) ) + 'px';
			} else if ( data.type === 'glocalsaino-aat:redirect' && typeof data.url === 'string' ) {
				try {
					const target = new URL( data.url, widgetUrl );
					if ( target.protocol === 'https:' || target.protocol === 'http:' ) {
						window.location.href = target.toString();
					}
				} catch ( e ) {}
			}
		} );
	}

	function run() {
		document.querySelectorAll( 'script[data-glocalsaino-aat-widget]:not([data-glocalsaino-aat-mounted])' ).forEach( mount );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
