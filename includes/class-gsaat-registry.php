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
	 * describe (callable( array $automation ): string, optional), visitor (bool: the event happens in
	 * the visitor's browser, so "logged-in visitor" conditions make sense).
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
			),
			'webhook' => array(
				'label'    => __( 'Data received through a webhook', 'glocalsaino-actions-automation-based-on-trigger' ),
				'sanitize' => array( 'GSAAT_Trigger_Webhook', 'sanitize_config' ),
				'describe' => array( 'GSAAT_Trigger_Webhook', 'describe' ),
				'visitor'  => false,
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
			)
		);
	}

	/**
	 * Registered action types.
	 *
	 * Each type: label (string), icon (dashicons class), defaults (array: initial settings of a new action),
	 * execute (callable( array $action, array $tokens ): array — build the result with GSAAT_Actions::result()),
	 * sanitize (callable( array $raw ): array|null), describe (callable( array $action ): string, optional).
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
				'label'    => '',
				'icon'     => 'dashicons-admin-generic',
				'defaults' => array(),
				'execute'  => null,
				'sanitize' => null,
				'describe' => null,
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
