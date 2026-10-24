<?php
/**
 * Registry of trigger and action types. The built-in types are registered here
 * through the same filters add-ons use, so both follow the same contract.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Registry {

	/**
	 * Registered trigger types.
	 *
	 * Each type: label (string), sanitize (callable( array $post, array &$notices ): array, optional),
	 * describe (callable( array $automation ): string, optional), visitor (bool: there is a user behind the
	 * event, so "logged-in visitor" conditions make sense), browser (bool: a browser is waiting for the
	 * response, so the visitor can be redirected).
	 *
	 * @return array[]
	 */
	public static function trigger_types() {
		$types = array(
			'cf7'     => array(
				'label'    => __( 'Contact Form 7 submission', 'glocalsaino-actions-automation-based-on-trigger' ),
				'sanitize' => array( 'GSAAT_Trigger_CF7', 'sanitize_config' ),
				'describe' => array( 'GSAAT_Trigger_CF7', 'describe' ),
				'visitor'  => true,
				'browser'  => true,
			),
			'webhook' => array(
				'label'    => __( 'Data received through a webhook', 'glocalsaino-actions-automation-based-on-trigger' ),
				'sanitize' => array( 'GSAAT_Trigger_Webhook', 'sanitize_config' ),
				'describe' => array( 'GSAAT_Trigger_Webhook', 'describe' ),
				'visitor'  => false,
				'browser'  => false,
			),
			'link'    => array(
				'label'    => __( 'Click on a link', 'glocalsaino-actions-automation-based-on-trigger' ),
				'sanitize' => array( 'GSAAT_Trigger_Click', 'sanitize_link' ),
				'describe' => array( 'GSAAT_Trigger_Click', 'describe_link' ),
				'visitor'  => true,
				'browser'  => true,
			),
			'button'  => array(
				'label'    => __( 'Click on a button', 'glocalsaino-actions-automation-based-on-trigger' ),
				'sanitize' => array( 'GSAAT_Trigger_Click', 'sanitize_button' ),
				'describe' => array( 'GSAAT_Trigger_Click', 'describe_button' ),
				'visitor'  => true,
				'browser'  => true,
			),
			'widget'  => array(
				'label'    => __( 'Embeddable widget on other websites (button, link or form)', 'glocalsaino-actions-automation-based-on-trigger' ),
				'sanitize' => array( 'GSAAT_Trigger_Widget', 'sanitize_config' ),
				'describe' => array( 'GSAAT_Trigger_Widget', 'describe' ),
				// Visitors on other sites are always anonymous here (this site's cookies are not sent).
				'visitor'  => false,
				'browser'  => true,
			),
			'file'    => array(
				'label'    => __( 'CSV or Excel file uploaded', 'glocalsaino-actions-automation-based-on-trigger' ),
				'sanitize' => array( 'GSAAT_Trigger_File', 'sanitize_config' ),
				'describe' => array( 'GSAAT_Trigger_File', 'describe' ),
				'visitor'  => true,
				'browser'  => true,
			),
			'wp_user' => array(
				'label'    => __( 'WordPress user event (registration, login, password change)', 'glocalsaino-actions-automation-based-on-trigger' ),
				'sanitize' => array( 'GSAAT_Trigger_User', 'sanitize_config' ),
				'describe' => array( 'GSAAT_Trigger_User', 'describe' ),
				'visitor'  => true,
				'browser'  => true,
			),
		);

		/**
		 * Filters the available trigger types.
		 *
		 * @param array[] $types Trigger types keyed by slug.
		 */
		$types = apply_filters( 'glocalsaino_aat_trigger_types', $types );

		return self::normalize(
			$types,
			array(
				'label'    => '',
				'sanitize' => null,
				'describe' => null,
				'visitor'  => false,
				'browser'  => false,
			)
		);
	}

	/**
	 * Registered action types.
	 *
	 * Each type: label (string), icon (dashicons class), defaults (array: initial settings of a new action),
	 * execute (callable( array $action, array $tokens ): array — build the result with GSAAT_Actions::result()),
	 * sanitize (callable( array $raw ): array|null), describe (callable( array $action ): string, optional),
	 * pure (bool: no side effects, only computes values — re-run freely on retries), needs_browser (bool),
	 * output_key (string: setting that holds the name of the value the action produces, e.g. "output";
	 * execute() returns it in the result's "tokens" so later actions can use it).
	 *
	 * @return array[]
	 */
	public static function action_types() {
		$types = array(
			'webhook' => array(
				'label'    => __( 'Send data to an external webhook', 'glocalsaino-actions-automation-based-on-trigger' ),
				'icon'     => 'dashicons-admin-links',
				'defaults' => array(
					'url'     => '',
					'method'  => 'POST',
					'format'  => 'json',
					'headers' => array(),
					'fields'  => array(),
				),
				'execute'  => array( 'GSAAT_Actions', 'webhook' ),
				'sanitize' => array( 'GSAAT_Actions', 'sanitize_webhook' ),
				'describe' => array( 'GSAAT_Actions', 'describe_webhook' ),
			),
			'email'   => array(
				'label'    => __( 'Send an email', 'glocalsaino-actions-automation-based-on-trigger' ),
				'icon'     => 'dashicons-email',
				'defaults' => array(
					'to'       => '',
					'reply_to' => '',
					'subject'  => '',
					'body'     => '',
				),
				'execute'  => array( 'GSAAT_Actions', 'email' ),
				'sanitize' => array( 'GSAAT_Actions', 'sanitize_email' ),
				'describe' => array( 'GSAAT_Actions', 'describe_email' ),
			),
			'calculator' => array(
				'label'      => __( 'Calculator', 'glocalsaino-actions-automation-based-on-trigger' ),
				'icon'       => 'dashicons-calculator',
				'defaults'   => array(
					'expression' => '',
					'decimals'   => '',
					'output'     => 'result',
				),
				'execute'    => array( 'GSAAT_Actions', 'calculator' ),
				'sanitize'   => array( 'GSAAT_Actions', 'sanitize_calculator' ),
				'describe'   => array( 'GSAAT_Actions', 'describe_calculator' ),
				'pure'       => true,
				'output_key' => 'output',
			),
			'format'     => array(
				'label'      => __( 'Format data', 'glocalsaino-actions-automation-based-on-trigger' ),
				'icon'       => 'dashicons-editor-textcolor',
				'defaults'   => array(
					'input'     => '',
					'operation' => 'first_word',
					'params'    => array( '', '', '' ),
					'output'    => 'formatted',
				),
				'execute'    => array( 'GSAAT_Actions', 'format' ),
				'sanitize'   => array( 'GSAAT_Actions', 'sanitize_format' ),
				'describe'   => array( 'GSAAT_Actions', 'describe_format' ),
				'pure'       => true,
				'output_key' => 'output',
			),
			'redirect'   => array(
				'label'         => __( 'Redirect the user', 'glocalsaino-actions-automation-based-on-trigger' ),
				'icon'          => 'dashicons-external',
				'defaults'      => array( 'url' => '' ),
				'execute'       => array( 'GSAAT_Actions', 'redirect' ),
				'sanitize'      => array( 'GSAAT_Actions', 'sanitize_redirect' ),
				'describe'      => array( 'GSAAT_Actions', 'describe_redirect' ),
				'needs_browser' => true,
			),
			'table'      => array(
				'label'    => __( 'Add rows to a table', 'glocalsaino-actions-automation-based-on-trigger' ),
				'icon'     => 'dashicons-editor-table',
				'defaults' => array(
					'table_id' => 0,
					'mode'     => 'add',
					'mapping'  => array(),
				),
				'execute'  => array( 'GSAAT_Actions', 'table' ),
				'sanitize' => array( 'GSAAT_Actions', 'sanitize_table' ),
				'describe' => array( 'GSAAT_Actions', 'describe_table' ),
			),
			'delay'      => array(
				'label'    => __( 'Wait (schedule the next actions)', 'glocalsaino-actions-automation-based-on-trigger' ),
				'icon'     => 'dashicons-clock',
				'defaults' => array(
					'amount' => 1,
					'unit'   => 'days',
				),
				// Handled by the engine: it schedules the actions that follow it.
				'execute'  => null,
				'sanitize' => array( 'GSAAT_Actions', 'sanitize_delay' ),
				'describe' => array( 'GSAAT_Actions', 'describe_delay' ),
			),
		);

		/**
		 * Filters the available action types.
		 *
		 * @param array[] $types Action types keyed by slug.
		 */
		$types = apply_filters( 'glocalsaino_aat_action_types', $types );

		return self::normalize(
			$types,
			array(
				'label'         => '',
				'icon'          => 'dashicons-admin-generic',
				'defaults'      => array(),
				'execute'       => null,
				'sanitize'      => null,
				'describe'      => null,
				'pure'          => false,
				'needs_browser' => false,
				'output_key'    => '',
			)
		);
	}

	/**
	 * One trigger type, or null when it is not registered.
	 *
	 * @param string $type Slug.
	 * @return array|null
	 */
	public static function trigger_type( $type ) {
		$types = self::trigger_types();
		return isset( $types[ $type ] ) ? $types[ $type ] : null;
	}

	/**
	 * One action type, or null when it is not registered.
	 *
	 * @param string $type Slug.
	 * @return array|null
	 */
	public static function action_type( $type ) {
		$types = self::action_types();
		return isset( $types[ $type ] ) ? $types[ $type ] : null;
	}

	/**
	 * Slugs of the trigger types whose events come from the visitor's browser.
	 *
	 * @return string[]
	 */
	public static function visitor_trigger_types() {
		return array_keys( wp_list_filter( self::trigger_types(), array( 'visitor' => true ) ) );
	}

	/**
	 * Slugs of the trigger types where a browser waits for the response (redirects possible).
	 *
	 * @return string[]
	 */
	public static function browser_trigger_types() {
		return array_keys( wp_list_filter( self::trigger_types(), array( 'browser' => true ) ) );
	}

	private static function normalize( $types, array $defaults ) {
		$clean = array();
		foreach ( (array) $types as $slug => $definition ) {
			$slug = sanitize_key( $slug );
			if ( '' === $slug || ! is_array( $definition ) || empty( $definition['label'] ) ) {
				continue;
			}
			$clean[ $slug ] = wp_parse_args( $definition, $defaults );
		}
		return $clean;
	}
}
