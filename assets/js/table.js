/**
 * Tables shown with [glocalsaino_aat_table]: search, column sorting and pagination.
 */
( function () {
	'use strict';

	const i18n = window.glocalsainoAatTable || {};

	function sortValue( cell ) {
		const raw = cell.getAttribute( 'data-sort' );
		return raw !== null && raw !== '' ? parseFloat( raw ) : null;
	}

	function enhance( wrap ) {
		const table = wrap.querySelector( 'table' );
		const tbody = table && table.tBodies[ 0 ];
		if ( ! tbody ) {
			return;
		}
		const perPage = parseInt( wrap.getAttribute( 'data-per-page' ), 10 ) || 0;
		const allRows = Array.from( tbody.rows );
		let visible = allRows.slice();
		let page = 0;
		let sortIndex = -1;
		let sortDir = 1;

		const empty = document.createElement( 'p' );
		empty.className = 'glocalsaino-aat-table__empty';
		empty.textContent = i18n.noRows || '';
		empty.hidden = true;

		const nav = document.createElement( 'nav' );
		nav.className = 'glocalsaino-aat-table__pages';
		const prev = document.createElement( 'button' );
		const next = document.createElement( 'button' );
		const info = document.createElement( 'span' );
		prev.type = next.type = 'button';
		prev.textContent = i18n.previous || '‹';
		next.textContent = i18n.next || '›';
		info.setAttribute( 'aria-live', 'polite' );
		nav.append( prev, info, next );

		function render() {
			const pages = perPage ? Math.max( 1, Math.ceil( visible.length / perPage ) ) : 1;
			page = Math.min( page, pages - 1 );
			const start = perPage ? page * perPage : 0;
			const end = perPage ? start + perPage : visible.length;
			const shown = new Set( visible.slice( start, end ) );
			allRows.forEach( ( row ) => ( row.hidden = ! shown.has( row ) ) );
			visible.forEach( ( row ) => tbody.appendChild( row ) );
			empty.hidden = visible.length > 0 || allRows.length === 0;
			nav.hidden = ! perPage || visible.length <= perPage;
			prev.disabled = page === 0;
			next.disabled = page >= pages - 1;
			info.textContent = ( i18n.showing || '%1$s–%2$s / %3$s' )
				.replace( '%1$s', visible.length ? start + 1 : 0 )
				.replace( '%2$s', Math.min( end, visible.length ) )
				.replace( '%3$s', visible.length );
		}

		prev.addEventListener( 'click', () => {
			page--;
			render();
		} );
		next.addEventListener( 'click', () => {
			page++;
			render();
		} );

		if ( wrap.getAttribute( 'data-search' ) === '1' && allRows.length ) {
			const search = document.createElement( 'input' );
			search.type = 'search';
			search.className = 'glocalsaino-aat-table__search';
			search.placeholder = i18n.search || '';
			search.setAttribute( 'aria-label', i18n.search || 'Search' );
			search.addEventListener( 'input', () => {
				const term = search.value.trim().toLowerCase();
				visible = allRows.filter( ( row ) => ! term || row.textContent.toLowerCase().includes( term ) );
				applySort();
				page = 0;
				render();
			} );
			wrap.insertBefore( search, wrap.querySelector( '.glocalsaino-aat-table__scroll' ) );
		}

		function applySort() {
			if ( sortIndex < 0 ) {
				return;
			}
			visible.sort( ( a, b ) => {
				const ca = a.cells[ sortIndex ];
				const cb = b.cells[ sortIndex ];
				const na = sortValue( ca );
				const nb = sortValue( cb );
				if ( na !== null && nb !== null ) {
					return ( na - nb ) * sortDir;
				}
				// Empty cells of a numeric / date column always go last.
				const ea = ca.textContent.trim() === '';
				const eb = cb.textContent.trim() === '';
				if ( ea !== eb ) {
					return ea ? 1 : -1;
				}
				return ca.textContent.localeCompare( cb.textContent, undefined, { numeric: true, sensitivity: 'base' } ) * sortDir;
			} );
		}

		if ( wrap.getAttribute( 'data-sort' ) === '1' ) {
			Array.from( table.tHead.rows[ 0 ].cells ).forEach( ( th, index ) => {
				const button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'glocalsaino-aat-table__sort';
				button.title = i18n.sortBy || '';
				while ( th.firstChild ) {
					button.appendChild( th.firstChild );
				}
				th.appendChild( button );
				button.addEventListener( 'click', () => {
					sortDir = sortIndex === index ? -sortDir : 1;
					sortIndex = index;
					table.querySelectorAll( 'th' ).forEach( ( cell ) => cell.removeAttribute( 'aria-sort' ) );
					th.setAttribute( 'aria-sort', sortDir === 1 ? 'ascending' : 'descending' );
					applySort();
					page = 0;
					render();
				} );
			} );
		}

		wrap.appendChild( empty );
		wrap.appendChild( nav );
		render();
	}

	function run() {
		document.querySelectorAll( '[data-glocalsaino-aat-table]' ).forEach( enhance );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
