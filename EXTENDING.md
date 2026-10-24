# Extending GlocalSaino Actions Automation

This plugin exposes an extension API so separate add-ons can add trigger types, action types, settings and admin screens without modifying it. The built-in triggers (Contact Form 7, webhook, link, button, embeddable widget, file upload, WordPress user events) and actions (webhook, email, calculator, format data, redirect, add rows to a table, wait) are registered through the same filters, so an add-on's types work the same way the built-in ones do.

API version: `GSAAT_API_VERSION` (currently `1.0`). It changes only when this contract changes.

## Bootstrapping an add-on

```php
add_action( 'glocalsaino_aat_loaded', function ( $api_version ) {
	if ( version_compare( $api_version, '1.0', '<' ) ) {
		return; // Core plugin too old.
	}
	// Register filters / actions here.
} );
```

If the core plugin is not active, `glocalsaino_aat_loaded` never fires, so the add-on stays idle.

## Action types

### PHP: register and execute

```php
add_filter( 'glocalsaino_aat_action_types', function ( $types ) {
	$types['myaddon_slack'] = array(
		'label'    => __( 'Send a Slack message', 'myaddon' ),
		'icon'     => 'dashicons-format-chat',
		'defaults' => array( 'webhook_url' => '', 'text' => '' ), // New action in the editor.
		'execute'  => 'myaddon_slack_execute',
		'sanitize' => 'myaddon_slack_sanitize',
		'describe' => function ( $action ) { return 'Slack: ' . $action['text']; }, // Automations list.
		// Optional flags:
		// 'pure'          => true,     // No side effects (only computes values): recomputed on retries.
		// 'needs_browser' => true,     // Only works when a browser waits for the response (see $context['browser']).
		// 'output_key'    => 'output', // Setting holding the name of the value this action produces.
	);
	return $types;
} );

// $tokens holds every {{field}} value of the event (plus values produced by previous actions).
// $context: user_id (logged-in visitor, 0 if none) and browser (true when a browser waits for the response).
// Return GSAAT_Actions::result().
function myaddon_slack_execute( array $action, array $tokens, array $context ) {
	$text     = GSAAT_Engine::replace_tokens( $action['text'], $tokens );
	$response = wp_safe_remote_post( $action['webhook_url'], array( 'body' => wp_json_encode( array( 'text' => $text ) ) ) );
	$ok       = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );

	return GSAAT_Actions::result( 'myaddon_slack', 'Slack', $ok, $ok ? 'Sent.' : 'Slack did not accept the message.', array(
		'sent' => array( 'text' => $text ), // Shown in the log detail.
	) );
}

// Receives the raw action posted by the editor. Return the clean settings (without 'type'), or null to drop it.
function myaddon_slack_sanitize( array $raw ) {
	return array(
		'webhook_url' => esc_url_raw( $raw['webhook_url'] ?? '' ),
		'text'        => sanitize_textarea_field( $raw['text'] ?? '' ),
	);
}
```

**Producing values for later actions.** Put them in the result's `tokens` key (`$result['tokens'] = array( 'my_value' => '42' );`). The following actions can use `{{my_value}}`. Declare `output_key` so the editor offers the name in the "Insert field…" pickers of the actions below. Actions after a **Wait** step receive the values produced before it.

Results are logged, count toward the automation status (success / partial / error), trigger the failure email, and can be retried from the Logs screen. A retry re-runs only the failed actions, matched by their exact settings. If the action was edited since the event, it is not retried.

If the add-on is deactivated, its actions stay stored in the automations. They are shown as "not available", fail with an explanatory message, and come back to life when the add-on is reactivated.

### JavaScript: the editor form

Register a script that depends on `glocalsaino-aat-editor-api` and add it to the editor dependencies:

```php
add_action( 'glocalsaino_aat_enqueue_editor_assets', function () {
	wp_register_script( 'myaddon-editor', plugins_url( 'editor.js', __FILE__ ), array( 'glocalsaino-aat-editor-api' ), '1.0', true );
} );
add_filter( 'glocalsaino_aat_editor_script_dependencies', function ( $handles ) {
	$handles[] = 'myaddon-editor';
	return $handles;
} );
```

```js
window.glocalsainoAat.registerActionType( 'myaddon_slack', {
	// Edit `action` in place; it is serialized on submit. Return an array of DOM nodes.
	render( action, h ) {
		return [
			h.labelled( 'Slack webhook URL', h.textControl( action, 'webhook_url', { placeholder: 'https://hooks.slack.com/…' } ) ),
			h.labelled( 'Message', h.textControl( action, 'text', { multiline: true } ) ), // Includes the "Insert field…" picker.
		];
	},
} );
```

Helpers (`h`): `el( tag, attrs, children )`, `labelled( label, control, hint )`, `textControl( action, prop, { multiline, placeholder } )`, `withPicker( input )`, `tokenPicker( input )`, `pairsEditor( list, addLabel, keyPlaceholder )`, `iconButton( icon, label, onClick )`, `i18n`, `cfg` (the `glocalsainoAatEditor` data, filterable with `glocalsaino_aat_editor_data`).

## Trigger types

```php
add_filter( 'glocalsaino_aat_trigger_types', function ( $types ) {
	$types['myaddon_woo_order'] = array(
		'label'    => __( 'WooCommerce order paid', 'myaddon' ),
		'visitor'  => false, // true if there is a user behind the event (enables "logged-in visitor" conditions).
		'browser'  => false, // true if a browser waits for the response (enables the Redirect action). Pass context['browser'] when firing.
		'sanitize' => function ( array $post, array &$notices ) {
			$config = array( 'min_total' => (float) ( $post['myaddon_min_total'] ?? 0 ) );
			// Add '_incomplete' => true to force the automation to be saved as inactive.
			return $config;
		},
		'describe' => function ( $automation ) {
			return 'Order paid ≥ ' . $automation['trigger_config']['min_total'];
		},
	);
	return $types;
} );

// Fire it when the event happens. Data is flattened (nested keys become "a.b").
add_action( 'woocommerce_payment_complete', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	GSAAT_Engine::fire(
		'myaddon_woo_order',
		array( 'order_id' => $order_id, 'total' => $order->get_total(), 'email' => $order->get_billing_email() ),
		sprintf( 'Order #%d paid', $order_id ), // Shown in the logs.
		array(
			'extra_tokens' => array( '_order_status' => $order->get_status() ),
			'context'      => array( 'user_id' => (int) $order->get_customer_id() ),
			'matches'      => function ( $automation ) use ( $order ) {
				return $order->get_total() >= $automation['trigger_config']['min_total'];
			},
		)
	);
} );
```

Trigger settings form: print it with the `glocalsaino_aat_trigger_settings` action, wrapped in `<div data-glocalsaino-aat-trigger="myaddon_woo_order" hidden>…</div>` so it shows only for that trigger. Field names are free (they arrive in `$post`).

Fields for the "Insert field…" pickers and conditions:

```js
window.glocalsainoAat.registerTriggerFields( 'myaddon_woo_order', () => [ 'order_id', 'total', 'email' ] );
```

## Data tables

Add-ons can create tables and fill them directly, without an automation (for example, the fields of an uploaded map layer):

```php
$table_id = GSAAT_Tables::create(
	'Layer: Municipios',
	array(
		array( 'key' => 'name', 'label' => 'Name', 'type' => 'text' ),
		array( 'key' => 'population', 'label' => 'Population', 'type' => 'number' ),
	),
	array( 'visibility' => 'everyone', 'search' => true )
);
GSAAT_Tables::add_rows( $table_id, $rows, array(), true ); // true = replace the existing rows.
```

`GSAAT_Tables::get()`, `::all()`, `::rows()`, `::count_rows()` and `::clear()` are available too. Keys of `$rows` that are not columns of the table are ignored. The `glocalsaino_aat_table_rows_added` action fires after rows are added.

Triggers that deliver many rows at once (like the file trigger in "once per file" mode) pass them in the context: `$context['rows']` (list of column => value) and `$context['columns']` (key => label). The "Add rows to a table" action uses them automatically.

## Per-automation add-on settings

Print extra cards in the editor with `glocalsaino_aat_editor_cards` (receives the automation). Store their values in the automation's `meta` array:

```php
add_filter( 'glocalsaino_aat_sanitize_automation_meta', function ( $meta, $post, $existing ) {
	$meta['myaddon_delay'] = absint( $post['myaddon_delay'] ?? 0 );
	return $meta;
}, 10, 3 );
```

Read it back from `$automation['meta']`.

## Settings, admin pages and notices

| Hook | Type | Use |
|---|---|---|
| `glocalsaino_aat_default_settings` | filter | Add default values for your settings keys. |
| `glocalsaino_aat_settings_fields` | action | Print fields in the Settings form. Use names like `glocalsaino_aat_settings[myaddon_key]`. |
| `glocalsaino_aat_sanitize_settings` | filter ( $clean, $input ) | Sanitize your keys (unknown keys are dropped otherwise). |
| `glocalsaino_aat_admin_menu` | action ( $parent_slug ) | `add_submenu_page( $parent_slug, … )` before Settings. |
| `glocalsaino_aat_admin_notices` | filter | Add notice codes (`code => [ type, message ]`), shown when the code is in the `glocalsaino_aat_msg` query arg (comma-separated). |

Read settings with `GSAAT_Plugin::settings()`.

## Runtime hooks

| Hook | Type | Arguments |
|---|---|---|
| `glocalsaino_aat_trigger_matches` | filter | bool, automation, data, context |
| `glocalsaino_aat_tokens` | filter | tokens, automation, context |
| `glocalsaino_aat_conditions_met` | filter | bool, automation, tokens, context |
| `glocalsaino_aat_condition_operators` | filter | operator => label |
| `glocalsaino_aat_compare` | filter | false, operator, value, expected (evaluate your operators) |
| `glocalsaino_aat_action_result` | filter | result, action, tokens |
| `glocalsaino_aat_automation_executed` | action | automation, status, results, log ID, tokens |
| `glocalsaino_aat_automation_failed` | action | automation, status (partial / error), results, log ID, tokens. Not fired by manual retries. |
| `glocalsaino_aat_automation_retried` | action | automation, status, results, new log ID, retried log ID |
| `glocalsaino_aat_automation_saved` | action | automation ID, data, post |
| `glocalsaino_aat_failure_email` | filter | { to, subject, body } (return false to cancel), automation, results, log ID |
| `glocalsaino_aat_sanitize_trigger_config` | filter | config, trigger type, post |
| `glocalsaino_aat_format_operations` | filter | key => [ label, [ [ param label, default ], … ] ] — add "Format data" operations |
| `glocalsaino_aat_format_value` | filter | null, operation, value, params — apply your operations (return a string) |

**Redirects from an add-on trigger.** If your trigger runs in a browser request, pass `'browser' => true` in the context and, after `GSAAT_Engine::fire()`, send the visitor to `GSAAT_Redirect::take()` when it is not empty.

Useful helpers: `GSAAT_Calculator::evaluate()`, `GSAAT_Calculator::to_number()`, `GSAAT_Formatter::apply()`, `GSAAT_Engine::replace_tokens()`, `GSAAT_Engine::flatten()`, `GSAAT_Engine::retry( $log_id )`, `GSAAT_Automations::get()`, `GSAAT_Logs::get()`, `GSAAT_Actions::result()`.

## WordPress.org note

The core plugin must stay fully functional on its own (Guideline 5): add-ons add new trigger and action types or new capabilities. They must not unlock limits that exist in the core plugin.
