/**
 * Extension API of the automation editor. Loaded before the editor and before any
 * add-on editor script, so add-ons can register their parts first.
 *
 *   window.glocalsainoAat.registerActionType( 'my_action', {
 *       render( action, helpers ) { return [ helpers.labelled( 'URL', helpers.textControl( action, 'url' ) ) ]; },
 *   } );
 *   window.glocalsainoAat.registerTriggerFields( 'my_trigger', () => [ 'order_id', 'total' ] );
 *
 * `helpers` (available once the editor runs): el, labelled, textControl, withPicker,
 * pairsEditor, tokenPicker, i18n, cfg.
 */
( function () {
	'use strict';

	if ( window.glocalsainoAat ) {
		return;
	}

	window.glocalsainoAat = {
		actionTypes: {},
		triggerFields: {},
		helpers: null,

		/**
		 * Register the editor form of an action type (the PHP side registers it with
		 * the glocalsaino_aat_action_types filter).
		 *
		 * @param {string} type Action type slug.
		 * @param {Object} definition { render( action, helpers ): Node[] } — edit `action` in place.
		 */
		registerActionType( type, definition ) {
			this.actionTypes[ type ] = definition;
		},

		/**
		 * Register the fields a trigger type provides, for the "Insert field…" pickers and conditions.
		 *
		 * @param {string}   type     Trigger type slug.
		 * @param {Function} provider Returns an array of field names.
		 */
		registerTriggerFields( type, provider ) {
			this.triggerFields[ type ] = provider;
		},
	};
} )();
