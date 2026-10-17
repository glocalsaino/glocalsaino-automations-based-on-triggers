/**
 * Automation editor: webhook keys, conditions and actions repeaters, field pickers.
 *
 * State lives in `state`; it is serialized into hidden inputs on submit and
 * sanitized again on the server.
 */
( function () {
	'use strict';

	const cfg = window.glocalsainoAatEditor;
	const form = document.getElementById( 'glocalsaino-aat-form' );
	if ( ! cfg || ! form ) {
		return;
	}

	const i18n = cfg.i18n;
	const state = {
		webhookKeys: Array.isArray( cfg.state.webhookKeys ) ? cfg.state.webhookKeys.slice() : [],
		conditions: {
			logic: cfg.state.conditions && cfg.state.conditions.logic === 'any' ? 'any' : 'all',
			rules: cfg.state.conditions && Array.isArray( cfg.state.conditions.rules ) ? cfg.state.conditions.rules : [],
		},
		actions: Array.isArray( cfg.state.actions ) ? cfg.state.actions : [],
	};

	const $ = ( id ) => document.getElementById( id );
	const triggerSelect = $( 'glocalsaino-aat-trigger' );
	const cf7FormSelect = $( 'glocalsaino-aat-cf7-form' );
	const cf7FieldSelect = $( 'glocalsaino-aat-cf7-field' );
	const cf7FieldRow = $( 'glocalsaino-aat-cf7-field-row' );
	const cf7ValueList = $( 'glocalsaino-aat-cf7-values' );
	const keysContainer = $( 'glocalsaino-aat-webhook-keys' );
	const conditionsContainer = $( 'glocalsaino-aat-conditions' );
	const actionsContainer = $( 'glocalsaino-aat-actions' );
	const logicSelect = $( 'glocalsaino-aat-condition-logic' );

	/* ------------------------------------------------------------------ */
	/* DOM helper                                                          */
	/* ------------------------------------------------------------------ */

	function el( tag, attrs, children ) {
		const node = document.createElement( tag );
		Object.entries( attrs || {} ).forEach( ( [ key, value ] ) => {
			if ( key === 'text' ) {
				node.textContent = value;
			} else if ( key === 'value' ) {
				node.value = value;
			} else if ( key.startsWith( 'on' ) && typeof value === 'function' ) {
				node.addEventListener( key.slice( 2 ), value );
			} else if ( value === true ) {
				node.setAttribute( key, '' );
			} else if ( value !== false && value !== null && value !== undefined ) {
				node.setAttribute( key, value );
			}
		} );
		( children || [] ).forEach( ( child ) => {
			if ( child ) {
				node.appendChild( typeof child === 'string' ? document.createTextNode( child ) : child );
			}
		} );
		return node;
	}

	function iconButton( icon, label, onClick, extraClass ) {
		return el( 'button', { type: 'button', class: 'button button-small glocalsaino-aat-icon-btn ' + ( extraClass || '' ), title: label, 'aria-label': label, onclick: onClick }, [
			el( 'span', { class: 'dashicons ' + icon, 'aria-hidden': 'true' } ),
		] );
	}

	function labelled( text, control, hint ) {
		return el( 'div', { class: 'glocalsaino-aat-field' }, [
			el( 'label', {}, [ el( 'span', { class: 'glocalsaino-aat-field__label', text } ), control ] ),
			hint ? el( 'p', { class: 'description', text: hint } ) : null,
		] );
	}

	/* ------------------------------------------------------------------ */
	/* Available fields                                                    */
	/* ------------------------------------------------------------------ */

	function currentCf7Form() {
		return ( cfg.cf7Forms || [] ).find( ( f ) => String( f.id ) === String( cf7FormSelect ? cf7FormSelect.value : '' ) );
	}

	function triggerFields() {
		const type = triggerSelect.value;
		if ( type === 'cf7' ) {
			const f = currentCf7Form();
			return f ? f.fields.map( ( field ) => field.name ) : [];
		}
		if ( type === 'webhook' ) {
			return state.webhookKeys.filter( Boolean );
		}
		return [];
	}

	function generalFields() {
		return cfg.builtinTokens.filter( ( t ) => ! t.trigger || t.trigger === triggerSelect.value );
	}

	/**
	 * Fill a <select> with the available fields.
	 *
	 * @param {HTMLSelectElement} select
	 * @param {string}            placeholder First (empty) option label.
	 * @param {string}            selected    Value to keep selected.
	 */
	function fillFieldOptions( select, placeholder, selected ) {
		select.textContent = '';
		select.appendChild( el( 'option', { value: '', text: placeholder } ) );

		const fields = triggerFields();
		const known = new Set();
		if ( fields.length ) {
			const group = el( 'optgroup', { label: i18n.triggerFields } );
			fields.forEach( ( name ) => {
				known.add( name );
				group.appendChild( el( 'option', { value: name, text: name } ) );
			} );
			select.appendChild( group );
		}
		const general = el( 'optgroup', { label: i18n.generalFields } );
		generalFields().forEach( ( t ) => {
			known.add( t.key );
			general.appendChild( el( 'option', { value: t.key, text: t.label } ) );
		} );
		select.appendChild( general );

		if ( selected && ! known.has( selected ) ) {
			select.appendChild( el( 'option', { value: selected, text: selected + ' ' + i18n.notAvailable } ) );
		}
		select.value = selected || '';
	}

	/**
	 * "Insert field…" picker that inserts {{field}} into an input at the cursor.
	 *
	 * @param {HTMLInputElement|HTMLTextAreaElement} target
	 */
	function tokenPicker( target ) {
		const select = el( 'select', { class: 'glocalsaino-aat-token-picker', 'aria-label': i18n.insertField } );
		select.addEventListener( 'change', () => {
			if ( ! select.value ) {
				return;
			}
			const token = '{{' + select.value + '}}';
			const start = target.selectionStart ?? target.value.length;
			const end = target.selectionEnd ?? target.value.length;
			target.value = target.value.slice( 0, start ) + token + target.value.slice( end );
			target.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			target.focus();
			target.setSelectionRange( start + token.length, start + token.length );
			select.value = '';
		} );
		fillFieldOptions( select, i18n.insertField, '' );
		return select;
	}

	function withPicker( input ) {
		return el( 'div', { class: 'glocalsaino-aat-with-picker' }, [ input, tokenPicker( input ) ] );
	}

	function refreshFieldPickers() {
		document.querySelectorAll( '.glocalsaino-aat-token-picker' ).forEach( ( s ) => fillFieldOptions( s, i18n.insertField, '' ) );
		document.querySelectorAll( '.glocalsaino-aat-condition-field' ).forEach( ( s ) => fillFieldOptions( s, i18n.chooseField, s.value ) );
		document.querySelectorAll( '.glocalsaino-aat-user-source' ).forEach( ( s ) => fillUserSource( s, s.value ) );
	}

	/* ------------------------------------------------------------------ */
	/* Trigger                                                             */
	/* ------------------------------------------------------------------ */

	function updateTriggerSections() {
		document.querySelectorAll( '[data-glocalsaino-aat-trigger]' ).forEach( ( section ) => {
			section.hidden = section.getAttribute( 'data-glocalsaino-aat-trigger' ) !== triggerSelect.value;
		} );
	}

	function cf7Mode() {
		const checked = form.querySelector( 'input[name="cf7_mode"]:checked' );
		return checked ? checked.value : 'form';
	}

	function updateCf7Mode() {
		if ( cf7FieldRow ) {
			cf7FieldRow.hidden = cf7Mode() !== 'field';
		}
	}

	function updateCf7Fields() {
		if ( ! cf7FieldSelect ) {
			return;
		}
		const current = cf7FieldSelect.value || cf7FieldSelect.getAttribute( 'data-selected' ) || '';
		const f = currentCf7Form();
		cf7FieldSelect.textContent = '';
		cf7FieldSelect.appendChild( el( 'option', { value: '', text: i18n.chooseField } ) );
		const names = [];
		( f ? f.fields : [] ).forEach( ( field ) => {
			if ( field.type === 'submit' ) {
				return;
			}
			names.push( field.name );
			cf7FieldSelect.appendChild( el( 'option', { value: field.name, text: field.name } ) );
		} );
		if ( current && ! names.includes( current ) ) {
			cf7FieldSelect.appendChild( el( 'option', { value: current, text: current + ' ' + i18n.notAvailable } ) );
		}
		cf7FieldSelect.value = current;
		cf7FieldSelect.removeAttribute( 'data-selected' );
		updateCf7Values();
	}

	function updateCf7Values() {
		if ( ! cf7ValueList ) {
			return;
		}
		cf7ValueList.textContent = '';
		const f = currentCf7Form();
		const field = f ? f.fields.find( ( x ) => x.name === cf7FieldSelect.value ) : null;
		( field ? field.options : [] ).forEach( ( option ) => cf7ValueList.appendChild( el( 'option', { value: option } ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Webhook keys                                                        */
	/* ------------------------------------------------------------------ */

	function webhookBaseUrl() {
		return cfg.webhookBase + $( 'glocalsaino-aat-webhook-key' ).value;
	}

	function updateWebhookExample() {
		const example = $( 'glocalsaino-aat-webhook-example' );
		if ( ! example ) {
			return;
		}
		const base = webhookBaseUrl();
		const keys = state.webhookKeys.filter( Boolean );
		const query = keys.map( ( k ) => encodeURIComponent( k ) + '=' + i18n.exampleValue ).join( '&' );
		example.textContent = query ? base + ( base.includes( '?' ) ? '&' : '?' ) + query : base;

		const headerLine = $( 'glocalsaino-aat-webhook-example-header' );
		const tokenEnabled = form.querySelector( 'input[name="sec_token_enabled"]' );
		if ( headerLine && tokenEnabled ) {
			headerLine.hidden = ! tokenEnabled.checked;
			headerLine.textContent = i18n.sendHeader + ' ' + $( 'glocalsaino-aat-sec-token-header' ).value + ': ' + $( 'glocalsaino-aat-sec-token-value' ).value;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Webhook security                                                    */
	/* ------------------------------------------------------------------ */

	function randomSecret( length ) {
		const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		const bytes = new Uint8Array( length );
		window.crypto.getRandomValues( bytes );
		return Array.from( bytes, ( b ) => chars[ b % chars.length ] ).join( '' );
	}

	function initSecurity() {
		document.querySelectorAll( '[data-glocalsaino-aat-toggle]' ).forEach( ( checkbox ) => {
			const body = $( checkbox.getAttribute( 'data-glocalsaino-aat-toggle' ) );
			const sync = () => {
				if ( body ) {
					body.hidden = ! checkbox.checked;
				}
				updateWebhookExample();
			};
			checkbox.addEventListener( 'change', sync );
			sync();
		} );

		document.querySelectorAll( '[data-glocalsaino-aat-generate]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const input = $( button.getAttribute( 'data-glocalsaino-aat-generate' ) );
				input.value = randomSecret( 40 );
				input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} );
		} );

		[ 'glocalsaino-aat-sec-token-header', 'glocalsaino-aat-sec-token-value' ].forEach( ( id ) => {
			const input = $( id );
			if ( input ) {
				input.addEventListener( 'input', updateWebhookExample );
			}
		} );

		const preset = $( 'glocalsaino-aat-sec-sig-preset' );
		if ( preset ) {
			const syncPreset = () => {
				document.querySelectorAll( '[data-glocalsaino-aat-preset-help]' ).forEach( ( p ) => {
					p.hidden = p.getAttribute( 'data-glocalsaino-aat-preset-help' ) !== preset.value;
				} );
				document.querySelectorAll( '[data-glocalsaino-aat-preset-only]' ).forEach( ( block ) => {
					block.hidden = ! block.getAttribute( 'data-glocalsaino-aat-preset-only' ).split( ' ' ).includes( preset.value );
				} );
			};
			preset.addEventListener( 'change', syncPreset );
			syncPreset();
		}
	}

	function renderWebhookKeys() {
		keysContainer.textContent = '';
		if ( ! state.webhookKeys.length ) {
			keysContainer.appendChild( el( 'p', { class: 'glocalsaino-aat-muted', text: i18n.noKeys } ) );
		}
		state.webhookKeys.forEach( ( key, index ) => {
			const input = el( 'input', {
				type: 'text',
				class: 'regular-text',
				value: key,
				placeholder: i18n.keyName,
				'aria-label': i18n.keyName,
				oninput: ( e ) => {
					state.webhookKeys[ index ] = e.target.value.trim();
					updateWebhookExample();
					refreshFieldPickers();
				},
			} );
			keysContainer.appendChild(
				el( 'div', { class: 'glocalsaino-aat-row' }, [
					input,
					iconButton( 'dashicons-trash', i18n.remove, () => {
						state.webhookKeys.splice( index, 1 );
						renderWebhookKeys();
						refreshFieldPickers();
					} ),
				] )
			);
		} );
		updateWebhookExample();
	}

	/* ------------------------------------------------------------------ */
	/* Conditions                                                          */
	/* ------------------------------------------------------------------ */

	function renderConditions() {
		conditionsContainer.textContent = '';
		if ( ! state.conditions.rules.length ) {
			conditionsContainer.appendChild( el( 'p', { class: 'glocalsaino-aat-muted', text: i18n.noConditions } ) );
		}
		state.conditions.rules.forEach( ( rule, index ) => {
			if ( rule.type === 'user' ) {
				conditionsContainer.appendChild( userConditionRow( rule, index ) );
				return;
			}
			const fieldSelect = el( 'select', {
				class: 'glocalsaino-aat-condition-field',
				'aria-label': i18n.chooseField,
				onchange: ( e ) => ( rule.field = e.target.value ),
			} );
			fillFieldOptions( fieldSelect, i18n.chooseField, rule.field );

			const operatorSelect = el( 'select', { 'aria-label': i18n.operator, onchange: ( e ) => {
				rule.operator = e.target.value;
				valueInput.hidden = isUnary( rule.operator );
			} } );
			Object.entries( cfg.operators ).forEach( ( [ key, label ] ) => operatorSelect.appendChild( el( 'option', { value: key, text: label } ) ) );
			operatorSelect.value = rule.operator || 'equals';
			rule.operator = operatorSelect.value;

			const valueInput = el( 'input', {
				type: 'text',
				value: rule.value || '',
				placeholder: i18n.value,
				'aria-label': i18n.value,
				oninput: ( e ) => ( rule.value = e.target.value ),
			} );
			valueInput.hidden = isUnary( rule.operator );

			conditionsContainer.appendChild(
				el( 'div', { class: 'glocalsaino-aat-row' }, [
					fieldSelect,
					operatorSelect,
					valueInput,
					iconButton( 'dashicons-trash', i18n.remove, () => {
						state.conditions.rules.splice( index, 1 );
						renderConditions();
					} ),
				] )
			);
		} );
	}

	function isUnary( operator ) {
		return operator === 'is_empty' || operator === 'not_empty';
	}

	/**
	 * "Identified by" select: the logged-in visitor, or a trigger field holding an email / username.
	 *
	 * @param {HTMLSelectElement} select
	 * @param {string}            selected
	 */
	function fillUserSource( select, selected ) {
		select.textContent = '';
		select.appendChild( el( 'option', { value: '', text: i18n.loggedInVisitor } ) );
		const fields = triggerFields();
		const group = el( 'optgroup', { label: i18n.emailInField } );
		fields.forEach( ( name ) => group.appendChild( el( 'option', { value: name, text: name } ) ) );
		if ( selected && ! fields.includes( selected ) ) {
			group.appendChild( el( 'option', { value: selected, text: selected + ' ' + i18n.notAvailable } ) );
		}
		if ( group.children.length ) {
			select.appendChild( group );
		}
		select.value = selected || '';
	}

	let pickerCount = 0;

	/**
	 * Registered user search (AJAX) for the "is the user" condition.
	 *
	 * @param {Object} rule
	 */
	function userPicker( rule ) {
		const listId = 'glocalsaino-aat-users-' + ++pickerCount;
		const input = el( 'input', {
			type: 'text',
			class: 'regular-text',
			list: listId,
			placeholder: i18n.searchUser,
			'aria-label': i18n.searchUser,
			value: rule.user_id ? cfg.userLabels[ rule.user_id ] || '#' + rule.user_id : '',
		} );
		const datalist = el( 'datalist', { id: listId } );
		let timer;

		input.addEventListener( 'input', () => {
			const match = input.value.match( /\(#(\d+)\)\s*$/ );
			rule.user_id = match ? parseInt( match[ 1 ], 10 ) : 0;
			input.classList.toggle( 'glocalsaino-aat-invalid', ! rule.user_id && input.value.trim() !== '' );
			if ( match ) {
				return;
			}
			clearTimeout( timer );
			const term = input.value.trim();
			if ( term.length < 2 ) {
				return;
			}
			timer = setTimeout( () => {
				const params = new URLSearchParams( { action: 'glocalsaino_aat_search_users', nonce: cfg.userNonce, term } );
				fetch( cfg.ajaxUrl + '?' + params.toString(), { credentials: 'same-origin' } )
					.then( ( response ) => response.json() )
					.then( ( result ) => {
						datalist.textContent = '';
						( result && result.success ? result.data : [] ).forEach( ( user ) => {
							cfg.userLabels[ user.id ] = user.label;
							datalist.appendChild( el( 'option', { value: user.label } ) );
						} );
					} )
					.catch( () => {} );
			}, 250 );
		} );

		return el( 'span', { class: 'glocalsaino-aat-user-picker' }, [ input, datalist ] );
	}

	function userConditionRow( rule, index ) {
		rule.operator = cfg.userOperators[ rule.operator ] ? rule.operator : 'logged_in';
		rule.user_field = rule.user_field || '';
		rule.user_id = parseInt( rule.user_id, 10 ) || 0;

		const operatorSelect = el( 'select', { 'aria-label': i18n.operator, onchange: ( e ) => {
			rule.operator = e.target.value;
			sync();
		} } );
		Object.entries( cfg.userOperators ).forEach( ( [ key, label ] ) => operatorSelect.appendChild( el( 'option', { value: key, text: label } ) ) );
		operatorSelect.value = rule.operator;

		const sourceLabel = el( 'span', { class: 'glocalsaino-aat-muted', text: i18n.identifiedBy } );
		const sourceSelect = el( 'select', { class: 'glocalsaino-aat-user-source', 'aria-label': i18n.identifiedBy, onchange: ( e ) => {
			rule.user_field = e.target.value;
			sync();
		} } );
		fillUserSource( sourceSelect, rule.user_field );

		const picker = userPicker( rule );
		const note = el( 'p', { class: 'description', text: i18n.webhookNoLogin } );

		function sync() {
			const needsSource = rule.operator === 'registered' || rule.operator === 'is_user';
			sourceLabel.hidden = ! needsSource;
			sourceSelect.hidden = ! needsSource;
			picker.hidden = rule.operator !== 'is_user';
			note.hidden = triggerSelect.value !== 'webhook' || ( needsSource && rule.user_field !== '' );
		}
		sync();

		return el( 'div', { class: 'glocalsaino-aat-user-rule' }, [
			el( 'div', { class: 'glocalsaino-aat-row' }, [
				el( 'span', { class: 'dashicons dashicons-admin-users', 'aria-hidden': 'true' } ),
				el( 'strong', { text: i18n.wpUser } ),
				operatorSelect,
				picker,
				sourceLabel,
				sourceSelect,
				iconButton( 'dashicons-trash', i18n.remove, () => {
					state.conditions.rules.splice( index, 1 );
					renderConditions();
				} ),
			] ),
			note,
		] );
	}

	/* ------------------------------------------------------------------ */
	/* Actions                                                             */
	/* ------------------------------------------------------------------ */

	function pairsEditor( list, addLabel, keyPlaceholder ) {
		const wrap = el( 'div', { class: 'glocalsaino-aat-pairs-editor' } );
		const rows = el( 'div', { class: 'glocalsaino-aat-repeater' } );

		function draw() {
			rows.textContent = '';
			list.forEach( ( pair, index ) => {
				const keyInput = el( 'input', {
					type: 'text',
					value: pair.key || '',
					placeholder: keyPlaceholder,
					'aria-label': keyPlaceholder,
					oninput: ( e ) => ( pair.key = e.target.value ),
				} );
				const valueInput = el( 'input', {
					type: 'text',
					class: 'glocalsaino-aat-grow',
					value: pair.value || '',
					placeholder: i18n.value,
					'aria-label': i18n.value,
					oninput: ( e ) => ( pair.value = e.target.value ),
				} );
				rows.appendChild(
					el( 'div', { class: 'glocalsaino-aat-row' }, [
						keyInput,
						withPicker( valueInput ),
						iconButton( 'dashicons-trash', i18n.remove, () => {
							list.splice( index, 1 );
							draw();
						} ),
					] )
				);
			} );
		}

		draw();
		wrap.appendChild( rows );
		wrap.appendChild(
			el( 'button', { type: 'button', class: 'button button-small', text: addLabel, onclick: () => {
				list.push( { key: '', value: '' } );
				draw();
			} } )
		);
		return wrap;
	}

	function textControl( action, prop, opts ) {
		const input = el( opts && opts.multiline ? 'textarea' : 'input', {
			type: opts && opts.multiline ? null : 'text',
			class: 'large-text',
			rows: opts && opts.multiline ? 8 : null,
			value: action[ prop ] || '',
			placeholder: opts && opts.placeholder ? opts.placeholder : null,
			oninput: ( e ) => ( action[ prop ] = e.target.value ),
		} );
		return withPicker( input );
	}

	function webhookActionBody( action ) {
		action.headers = Array.isArray( action.headers ) ? action.headers : [];
		action.fields = Array.isArray( action.fields ) ? action.fields : [];
		action.method = action.method || 'POST';
		action.format = action.format || 'json';

		const methodSelect = el( 'select', { onchange: ( e ) => {
			action.method = e.target.value;
			updateMethodDependent();
		} } );
		cfg.methods.forEach( ( m ) => methodSelect.appendChild( el( 'option', { value: m, text: m } ) ) );
		methodSelect.value = action.method;

		const formatSelect = el( 'select', { onchange: ( e ) => ( action.format = e.target.value ) } );
		formatSelect.appendChild( el( 'option', { value: 'json', text: i18n.formatJson } ) );
		formatSelect.appendChild( el( 'option', { value: 'form', text: i18n.formatForm } ) );
		formatSelect.value = action.format;

		const formatField = labelled( i18n.format, formatSelect );
		const getHint = el( 'p', { class: 'description', text: i18n.dataGetHint } );

		function updateMethodDependent() {
			const queryOnly = action.method === 'GET' || action.method === 'DELETE';
			formatField.hidden = queryOnly;
			getHint.hidden = ! queryOnly;
		}
		updateMethodDependent();

		return [
			labelled( i18n.url, textControl( action, 'url', { placeholder: 'https://' } ) ),
			el( 'div', { class: 'glocalsaino-aat-inline' }, [ labelled( i18n.method, methodSelect ), formatField ] ),
			el( 'h4', { text: i18n.dataToSend } ),
			getHint,
			pairsEditor( action.fields, i18n.addPair, i18n.key ),
			el( 'details', { class: 'glocalsaino-aat-details-toggle', open: action.headers.length > 0 }, [
				el( 'summary', { text: i18n.headers } ),
				pairsEditor( action.headers, i18n.addHeader, 'Authorization' ),
			] ),
		];
	}

	function emailActionBody( action ) {
		return [
			labelled( i18n.to, textControl( action, 'to' ), i18n.toHint ),
			labelled( i18n.replyTo, textControl( action, 'reply_to' ) ),
			labelled( i18n.subject, textControl( action, 'subject' ) ),
			labelled( i18n.body, textControl( action, 'body', { multiline: true } ) ),
		];
	}

	function renderActions() {
		actionsContainer.textContent = '';
		if ( ! state.actions.length ) {
			actionsContainer.appendChild( el( 'p', { class: 'glocalsaino-aat-muted', text: i18n.noActions } ) );
		}
		state.actions.forEach( ( action, index ) => {
			const title = ( index + 1 ) + '. ' + ( action.type === 'email' ? i18n.actionEmail : i18n.actionWebhook );
			const move = ( delta ) => {
				const target = index + delta;
				if ( target < 0 || target >= state.actions.length ) {
					return;
				}
				state.actions.splice( target, 0, state.actions.splice( index, 1 )[ 0 ] );
				renderActions();
			};

			actionsContainer.appendChild(
				el( 'div', { class: 'glocalsaino-aat-action' }, [
					el( 'div', { class: 'glocalsaino-aat-action__header' }, [
						el( 'span', { class: 'dashicons ' + ( action.type === 'email' ? 'dashicons-email' : 'dashicons-admin-links' ), 'aria-hidden': 'true' } ),
						el( 'strong', { text: title } ),
						el( 'span', { class: 'glocalsaino-aat-action__tools' }, [
							index > 0 ? iconButton( 'dashicons-arrow-up-alt2', i18n.moveUp, () => move( -1 ) ) : null,
							index < state.actions.length - 1 ? iconButton( 'dashicons-arrow-down-alt2', i18n.moveDown, () => move( 1 ) ) : null,
							iconButton( 'dashicons-trash', i18n.remove, () => {
								state.actions.splice( index, 1 );
								renderActions();
							} ),
						] ),
					] ),
					el( 'div', { class: 'glocalsaino-aat-action__body' }, action.type === 'email' ? emailActionBody( action ) : webhookActionBody( action ) ),
				] )
			);
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Runs / dates                                                        */
	/* ------------------------------------------------------------------ */

	function linkCheckbox( checkboxId, inputId ) {
		const checkbox = $( checkboxId );
		const input = $( inputId );
		if ( ! checkbox || ! input ) {
			return;
		}
		const sync = () => {
			input.disabled = checkbox.checked;
			input.required = ! checkbox.checked;
		};
		checkbox.addEventListener( 'change', sync );
		sync();
	}

	/* ------------------------------------------------------------------ */
	/* Wiring                                                              */
	/* ------------------------------------------------------------------ */

	triggerSelect.addEventListener( 'change', () => {
		updateTriggerSections();
		refreshFieldPickers();
		renderConditions();
	} );
	if ( cf7FormSelect ) {
		cf7FormSelect.addEventListener( 'change', () => {
			updateCf7Fields();
			refreshFieldPickers();
		} );
		cf7FieldSelect.addEventListener( 'change', updateCf7Values );
		form.querySelectorAll( 'input[name="cf7_mode"]' ).forEach( ( radio ) => radio.addEventListener( 'change', updateCf7Mode ) );
	}

	$( 'glocalsaino-aat-add-key' ).addEventListener( 'click', () => {
		state.webhookKeys.push( '' );
		renderWebhookKeys();
		const inputs = keysContainer.querySelectorAll( 'input' );
		if ( inputs.length ) {
			inputs[ inputs.length - 1 ].focus();
		}
	} );

	logicSelect.value = state.conditions.logic;
	logicSelect.addEventListener( 'change', () => ( state.conditions.logic = logicSelect.value ) );
	$( 'glocalsaino-aat-add-condition' ).addEventListener( 'click', () => {
		state.conditions.rules.push( { field: '', operator: 'equals', value: '' } );
		renderConditions();
	} );
	$( 'glocalsaino-aat-add-user-condition' ).addEventListener( 'click', () => {
		state.conditions.rules.push( { type: 'user', operator: 'logged_in', user_field: '', user_id: 0 } );
		renderConditions();
	} );

	form.querySelectorAll( '[data-add-action]' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			const type = button.getAttribute( 'data-add-action' );
			state.actions.push(
				type === 'email'
					? { type: 'email', to: '', reply_to: '', subject: '', body: '' }
					: { type: 'webhook', url: '', method: 'POST', format: 'json', headers: [], fields: [] }
			);
			renderActions();
		} );
	} );

	document.querySelectorAll( '.glocalsaino-aat-copy' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			const source = $( button.getAttribute( 'data-copy-target' ) );
			if ( ! source || ! navigator.clipboard ) {
				return;
			}
			navigator.clipboard.writeText( source.textContent.trim() ).then( () => {
				const original = button.textContent;
				button.textContent = i18n.copied;
				setTimeout( () => ( button.textContent = original ), 1500 );
			} );
		} );
	} );

	form.addEventListener( 'submit', ( e ) => {
		if ( triggerSelect.value === 'cf7' && cf7FormSelect && cf7FormSelect.value === '0' && cf7FormSelect.options.length > 1 ) {
			e.preventDefault();
			window.alert( i18n.errorForm );
			cf7FormSelect.focus();
			return;
		}
		if ( triggerSelect.value === 'cf7' && cf7Mode() === 'field' && cf7FieldSelect && ! cf7FieldSelect.value ) {
			e.preventDefault();
			window.alert( i18n.errorField );
			cf7FieldSelect.focus();
			return;
		}

		if ( state.conditions.rules.some( ( r ) => r.type === 'user' && r.operator === 'is_user' && ! r.user_id ) ) {
			e.preventDefault();
			window.alert( i18n.errorUser );
			return;
		}

		const emptyPair = state.actions.some(
			( a ) => a.type === 'webhook' && [ ...( a.fields || [] ), ...( a.headers || [] ) ].some( ( p ) => ! String( p.key || '' ).trim() && String( p.value || '' ).trim() )
		);
		if ( emptyPair ) {
			e.preventDefault();
			window.alert( i18n.missingKeyValue );
			return;
		}

		$( 'glocalsaino-aat-webhook-keys-json' ).value = JSON.stringify( state.webhookKeys.filter( Boolean ) );
		$( 'glocalsaino-aat-conditions-json' ).value = JSON.stringify( state.conditions );
		$( 'glocalsaino-aat-actions-json' ).value = JSON.stringify( state.actions );
	} );

	linkCheckbox( 'glocalsaino-aat-unlimited-runs', 'glocalsaino-aat-max-runs' );
	linkCheckbox( 'glocalsaino-aat-never-expires', 'glocalsaino-aat-end-date' );

	initSecurity();
	updateTriggerSections();
	updateCf7Fields();
	updateCf7Mode();
	renderWebhookKeys();
	renderConditions();
	renderActions();
} )();
