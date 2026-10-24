<?php
/**
 * Trigger: data received through a webhook (REST endpoint).
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Trigger_Webhook {

	const MAX_FIELDS       = 200;
	const MAX_VALUE_LENGTH = 10000;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			GSAAT_Automations::REST_NAMESPACE,
			'/webhook/(?P<key>[A-Za-z0-9]{20,64})',
			array(
				'methods'             => array( 'GET', 'POST', 'PUT' ),
				'callback'            => array( __CLASS__, 'handle' ),
				// Public by design: external systems call it, the unguessable key in the URL identifies the automation.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle an incoming webhook call.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle( WP_REST_Request $request ) {
		$automation = GSAAT_Automations::get_by_webhook_key( (string) $request->get_url_params()['key'] );
		if ( ! $automation || 'webhook' !== $automation['trigger_type'] ) {
			return new WP_Error( 'glocalsaino_aat_not_found', __( 'Unknown webhook.', 'glocalsaino-automations-based-on-triggers' ), array( 'status' => 404 ) );
		}

		$method   = $request->get_method();
		$received = self::received_data( $request );
		$ip       = GSAAT_Webhook_Security::client_ip();
		$security = GSAAT_Webhook_Security::config( $automation['trigger_config'] );
		$log      = array(
			'automation_id'   => $automation['id'],
			'automation_name' => $automation['name'],
			'webhook_url'     => self::request_url( $request ),
			'method'          => $method,
			'remote_ip'       => $ip,
			'received_data'   => $received,
		);

		if ( GSAAT_Webhook_Security::is_woocommerce_ping( $security, $request ) ) {
			GSAAT_Logs::add_webhook(
				$log + array(
					'status_code'   => 200,
					'error_message' => __( 'WooCommerce delivery URL test (ping) accepted. No action was executed.', 'glocalsaino-automations-based-on-triggers' ),
				)
			);
			return new WP_REST_Response( array( 'success' => true ), 200 );
		}

		$check = GSAAT_Webhook_Security::check( $security, $request, $ip );
		if ( true !== $check ) {
			return self::fail( $log, $check[0], $check[1], $check[2] );
		}

		if ( ! $received ) {
			return self::fail( $log, 400, 'glocalsaino_aat_no_data', __( 'No data was received.', 'glocalsaino-automations-based-on-triggers' ) );
		}

		$keys = isset( $automation['trigger_config']['keys'] ) ? (array) $automation['trigger_config']['keys'] : array();
		$data = $received;
		if ( $keys ) {
			$data = array();
			foreach ( $keys as $key ) {
				$data[ $key ] = isset( $received[ $key ] ) ? $received[ $key ] : '';
			}
			if ( ! array_intersect_key( $received, array_flip( $keys ) ) ) {
				return self::fail(
					$log,
					400,
					'glocalsaino_aat_missing_keys',
					/* translators: %s: comma-separated list of keys. */
					sprintf( __( 'None of the configured keys were received (expected: %s).', 'glocalsaino-automations-based-on-triggers' ), implode( ', ', $keys ) )
				);
			}
		}

		$availability = GSAAT_Automations::availability( $automation );
		if ( 'active' !== $availability ) {
			$errors = array(
				'inactive'      => array( 403, __( 'The automation is inactive.', 'glocalsaino-automations-based-on-triggers' ) ),
				'scheduled'     => array( 403, __( 'The automation has not started yet.', 'glocalsaino-automations-based-on-triggers' ) ),
				'expired'       => array( 410, __( 'The automation has expired.', 'glocalsaino-automations-based-on-triggers' ) ),
				'limit_reached' => array( 410, __( 'The automation has reached its maximum number of runs.', 'glocalsaino-automations-based-on-triggers' ) ),
			);
			return self::fail( $log, $errors[ $availability ][0], 'glocalsaino_aat_' . $availability, $errors[ $availability ][1] );
		}

		GSAAT_Logs::add_webhook( $log + array( 'status_code' => 200 ) );

		$status = GSAAT_Engine::process(
			$automation,
			$data,
			/* translators: %s: HTTP method. */
			sprintf( __( 'Webhook received (%s)', 'glocalsaino-automations-based-on-triggers' ), $method ),
			array( '_webhook_method' => $method ),
			array( 'user_id' => 0 )
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'status'  => $status,
			),
			200
		);
	}

	/**
	 * Sanitize the trigger settings posted by the automation editor.
	 *
	 * @param array $post    Unslashed POST data.
	 * @param array $notices Notice codes, appended to.
	 * @return array
	 */
	public static function sanitize_config( array $post, array &$notices ) {
		$raw  = json_decode( isset( $post['webhook_keys_json'] ) ? (string) $post['webhook_keys_json'] : '[]', true );
		$keys = array();
		foreach ( is_array( $raw ) ? $raw : array() as $key ) {
			$key = is_string( $key ) ? GSAAT_Engine::sanitize_field_name( $key ) : '';
			if ( '' !== $key && ! in_array( $key, $keys, true ) ) {
				$keys[] = $key;
			}
		}
		return array(
			'keys'     => $keys,
			'security' => GSAAT_Webhook_Security::sanitize( $post, $notices ),
		);
	}

	/**
	 * Description for the automations list.
	 *
	 * @param array $automation Automation.
	 * @return string Plain text.
	 */
	public static function describe( array $automation ) {
		$config    = $automation['trigger_config'];
		$keys      = isset( $config['keys'] ) ? (array) $config['keys'] : array();
		$protected = GSAAT_Webhook_Security::enabled_labels( GSAAT_Webhook_Security::config( $config ) );
		$text      = $keys
			/* translators: %s: comma-separated list of keys. */
			? sprintf( __( 'Webhook (keys: %s)', 'glocalsaino-automations-based-on-triggers' ), implode( ', ', $keys ) )
			: __( 'Webhook (all received data)', 'glocalsaino-automations-based-on-triggers' );
		if ( $protected ) {
			/* translators: %s: comma-separated list of protections. */
			$text .= ' — ' . sprintf( __( 'protected by %s', 'glocalsaino-automations-based-on-triggers' ), implode( ', ', $protected ) );
		}
		return $text;
	}

	/**
	 * Query string, form body and JSON body merged and flattened.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function received_data( WP_REST_Request $request ) {
		// get_params() makes WordPress parse url-encoded bodies of PUT requests too.
		$request->get_params();

		$query = (array) $request->get_query_params();
		unset( $query['rest_route'] );
		$json = $request->get_json_params();

		$data = self::flatten_limited(
			array_merge( $query, (array) $request->get_body_params(), is_array( $json ) ? $json : array() )
		);
		return $data;
	}

	private static function flatten_limited( array $raw ) {
		$flat = array_slice( GSAAT_Engine::flatten( $raw ), 0, self::MAX_FIELDS, true );
		foreach ( $flat as $key => $value ) {
			$flat[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::MAX_VALUE_LENGTH ) : substr( $value, 0, self::MAX_VALUE_LENGTH );
		}
		return $flat;
	}

	private static function request_url( WP_REST_Request $request ) {
		$url   = rest_url( ltrim( $request->get_route(), '/' ) );
		$query = (array) $request->get_query_params();
		unset( $query['rest_route'] );
		if ( $query ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}
		return $url;
	}

	private static function fail( array $log, $status_code, $error_code, $message ) {
		GSAAT_Logs::add_webhook(
			$log + array(
				'status_code'   => $status_code,
				'error_code'    => $error_code,
				'error_message' => $message,
			)
		);
		return new WP_Error( $error_code, $message, array( 'status' => $status_code ) );
	}
}
