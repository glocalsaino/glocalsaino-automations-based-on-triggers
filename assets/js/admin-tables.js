/**
 * Table editor: columns (field name, title, type) and import of columns from a file header.
 */
( function () {
	'use strict';

	const cfg = window.glocalsainoAatTables;
	const form = document.getElementById( 'glocalsaino-aat-table-form' );
	const container = document.getElementById( 'glocalsaino-aat-columns' );
	if ( ! cfg || ! form || ! container ) {
		return;
	}
	const i18n = cfg.i18n;
	const columns = Array.isArray( cfg.columns ) ? cfg.columns : [];

	function keyFrom( text ) {
		return String( text || '' )
			.normalize( 'NFD' )
			.replace( /[̀-ͯ]/g, '' )
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' )
			.slice( 0, 64 );
	}

	function button( icon, label, onClick ) {
		const b = document.createElement( 'button' );
		b.type = 'button';
		b.className = 'button button-small glocalsaino-aat-icon-btn';
		b.title = label;
		b.setAttribute( 'aria-label', label );
		const span = document.createElement( 'span' );
		span.className = 'dashicons ' + icon;
		span.setAttribute( 'aria-hidden', 'true' );
		b.appendChild( span );
		b.addEventListener( 'click', onClick );
		return b;
	}

	function render() {
		container.textContent = '';
		if ( ! columns.length ) {
			const p = document.createElement( 'p' );
			p.className = 'glocalsaino-aat-muted';
			p.textContent = i18n.none;
			container.appendChild( p );
		}
		columns.forEach( ( column, index ) => {
			const row = document.createElement( 'div' );
			row.className = 'glocalsaino-aat-row';

			const label = document.createElement( 'input' );
			label.type = 'text';
			label.value = column.label || '';
			label.placeholder = i18n.label;
			label.setAttribute( 'aria-label', i18n.label );

			const key = document.createElement( 'input' );
			key.type = 'text';
			key.className = 'code';
			key.value = column.key || '';
			key.placeholder = i18n.key;
			key.setAttribute( 'aria-label', i18n.key );
			key.readOnly = !! column.saved;

			label.addEventListener( 'input', () => {
				column.label = label.value;
				if ( ! column.saved && ! column.keyEdited ) {
					column.key = keyFrom( label.value );
					key.value = column.key;
				}
			} );
			key.addEventListener( 'input', () => {
				column.keyEdited = true;
				column.key = keyFrom( key.value );
				if ( key.value !== column.key ) {
					key.value = column.key;
				}
			} );

			const type = document.createElement( 'select' );
			type.setAttribute( 'aria-label', i18n.type );
			Object.entries( cfg.types ).forEach( ( [ value, text ] ) => {
				const option = document.createElement( 'option' );
				option.value = value;
				option.textContent = text;
				type.appendChild( option );
			} );
			type.value = column.type || 'text';
			column.type = type.value;
			type.addEventListener( 'change', () => ( column.type = type.value ) );

			const move = ( delta ) => {
				const target = index + delta;
				if ( target >= 0 && target < columns.length ) {
					columns.splice( target, 0, columns.splice( index, 1 )[ 0 ] );
					render();
				}
			};

			row.append(
				label,
				key,
				type,
				index > 0 ? button( 'dashicons-arrow-up-alt2', i18n.moveUp, () => move( -1 ) ) : '',
				index < columns.length - 1 ? button( 'dashicons-arrow-down-alt2', i18n.moveDown, () => move( 1 ) ) : '',
				button( 'dashicons-trash', i18n.remove, () => {
					columns.splice( index, 1 );
					render();
				} )
			);
			container.appendChild( row );
		} );
	}

	document.getElementById( 'glocalsaino-aat-add-column' ).addEventListener( 'click', () => {
		columns.push( { key: '', label: '', type: 'text' } );
		render();
		const inputs = container.querySelectorAll( 'input[type="text"]:not(.code)' );
		if ( inputs.length ) {
			inputs[ inputs.length - 1 ].focus();
		}
	} );

	const fileInput = document.getElementById( 'glocalsaino-aat-columns-file' );
	const status = document.getElementById( 'glocalsaino-aat-columns-status' );
	fileInput.addEventListener( 'change', () => {
		if ( ! fileInput.files.length ) {
			return;
		}
		const data = new FormData();
		data.append( 'action', 'glocalsaino_aat_detect_columns' );
		data.append( 'nonce', cfg.nonce );
		data.append( 'file', fileInput.files[ 0 ] );
		status.textContent = i18n.reading;
		window.fetch( cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' } )
			.then( ( response ) => response.json() )
			.then( ( result ) => {
				if ( ! result.success ) {
					throw new Error( ( result.data && result.data.message ) || i18n.error );
				}
				const existing = new Set( columns.map( ( c ) => c.key ) );
				result.data.columns.forEach( ( column ) => {
					if ( ! existing.has( column.key ) ) {
						columns.push( { key: column.key, label: column.label, type: 'text' } );
					}
				} );
				render();
				status.textContent = i18n.imported;
			} )
			.catch( ( error ) => ( status.textContent = error.message || i18n.error ) )
			.then( () => ( fileInput.value = '' ) );
	} );

	form.addEventListener( 'submit', ( event ) => {
		if ( columns.some( ( column ) => ! column.key ) ) {
			event.preventDefault();
			window.alert( i18n.noKeys );
			return;
		}
		document.getElementById( 'glocalsaino-aat-columns-json' ).value = JSON.stringify(
			columns.map( ( column ) => ( { key: column.key, label: column.label, type: column.type } ) )
		);
	} );

	document.querySelectorAll( '.glocalsaino-aat-copy' ).forEach( ( copy ) => {
		copy.addEventListener( 'click', () => {
			const source = document.getElementById( copy.getAttribute( 'data-copy-target' ) );
			if ( source && navigator.clipboard ) {
				navigator.clipboard.writeText( source.textContent.trim() );
			}
		} );
	} );

	render();
} )();
