<?php
/**
 * Actions executed by automations.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Actions {

	/**
	 * Available action types.
	 *
	 * @return array
	 */
	public static function types() {
		return wp_list_pluck( GSAAT_Registry::action_types(), 'label' );
	}

	/**
	 * HTTP methods allowed for outgoing webhooks.
	 *
	 * @return string[]
	 */
	public static function http_methods() {
		return array( 'POST', 'GET', 'PUT', 'PATCH', 'DELETE' );
	}

	/**
	 * Execute one action through its registered type.
	 *
	 * @param array $action Action configuration.
	 * @param array $tokens Token values.
	 * @return array { type, target, success, message, details }
	 */
	public static function execute( array $action, array $tokens ) {
		$type       = isset( $action['type'] ) ? (string) $action['type'] : '';
		$definition = GSAAT_Registry::action_type( $type );

		if ( ! $definition || ! is_callable( $definition['execute'] ) ) {
			return self::result( $type, '', false, __( 'This action type is not available. If it comes from an add-on, check that the add-on is active.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}

		$result = call_user_func( $definition['execute'], $action, $tokens );
		$result = is_array( $result )
			? wp_parse_args( $result, self::result( $type, '', false, '' ) )
			: self::result( $type, '', false, __( 'The action did not return a valid result.', 'glocalsaino-actions-automation-based-on-trigger' ) );

		/**
		 * Filters the result of an executed action.
		 *
		 * @param array $result { type, target, success, message, details }.
		 * @param array $action Action configuration.
		 * @param array $tokens Tokens.
		 */
		return apply_filters( 'glocalsaino_aat_action_result', $result, $action, $tokens );
	}

	/**
	 * Sanitize the actions posted by the editor. Actions of unregistered types (e.g. from a
	 * deactivated add-on) are kept with every text value sanitized, so they are not lost.
	 *
	 * @param mixed $actions Decoded JSON.
	 * @return array
	 */
	public static function sanitize_all( $actions ) {
		$clean = array();
		foreach ( is_array( $actions ) ? $actions : array() as $action ) {
			if ( ! is_array( $action ) || empty( $action['type'] ) ) {
				continue;
			}
			$type       = sanitize_key( $action['type'] );
			$definition = GSAAT_Registry::action_type( $type );
			if ( ! $definition ) {
				$clean[] = map_deep( $action, 'sanitize_text_field' );
				continue;
			}
			$sanitized = is_callable( $definition['sanitize'] ) ? call_user_func( $definition['sanitize'], $action ) : null;
			if ( is_array( $sanitized ) ) {
				$clean[] = array( 'type' => $type ) + $sanitized;
			}
		}
		return $clean;
	}

	public static function sanitize_webhook( array $action ) {
		$method = isset( $action['method'] ) ? strtoupper( (string) $action['method'] ) : 'POST';
		return array(
			'url'     => isset( $action['url'] ) ? sanitize_text_field( (string) $action['url'] ) : '',
			'method'  => in_array( $method, self::http_methods(), true ) ? $method : 'POST',
			'format'  => ( isset( $action['format'] ) && 'form' === $action['format'] ) ? 'form' : 'json',
			'headers' => self::sanitize_pairs( isset( $action['headers'] ) ? $action['headers'] : array() ),
			'fields'  => self::sanitize_pairs( isset( $action['fields'] ) ? $action['fields'] : array() ),
		);
	}

	public static function sanitize_email( array $action ) {
		return array(
			'to'       => isset( $action['to'] ) ? sanitize_text_field( (string) $action['to'] ) : '',
			'reply_to' => isset( $action['reply_to'] ) ? sanitize_text_field( (string) $action['reply_to'] ) : '',
			'subject'  => isset( $action['subject'] ) ? sanitize_text_field( (string) $action['subject'] ) : '',
			'body'     => isset( $action['body'] ) ? sanitize_textarea_field( (string) $action['body'] ) : '',
		);
	}

	/**
	 * Sanitize a list of { key, value } pairs, dropping rows without a key.
	 *
	 * @param mixed $pairs Raw pairs.
	 * @return array
	 */
	public static function sanitize_pairs( $pairs ) {
		$clean = array();
		foreach ( is_array( $pairs ) ? $pairs : array() as $pair ) {
			if ( ! is_array( $pair ) ) {
				continue;
			}
			$key = isset( $pair['key'] ) ? sanitize_text_field( (string) $pair['key'] ) : '';
			if ( '' === $key ) {
				continue;
			}
			$clean[] = array(
				'key'   => $key,
				'value' => isset( $pair['value'] ) ? sanitize_text_field( (string) $pair['value'] ) : '',
			);
		}
		return $clean;
	}

	/**
	 * Human description of one action, for the automations list.
	 *
	 * @param array $action Action.
	 * @return string Plain text.
	 */
	public static function describe( array $action ) {
		$definition = GSAAT_Registry::action_type( isset( $action['type'] ) ? $action['type'] : '' );
		if ( ! $definition ) {
			/* translators: %s: action type slug. */
			return sprintf( __( 'Unavailable action (%s)', 'glocalsaino-actions-automation-based-on-trigger' ), isset( $action['type'] ) ? $action['type'] : '?' );
		}
		return is_callable( $definition['describe'] ) ? (string) call_user_func( $definition['describe'], $action ) : $definition['label'];
	}

	public static function describe_webhook( array $action ) {
		$host = wp_parse_url( $action['url'], PHP_URL_HOST );
		/* translators: 1: HTTP method, 2: host name or URL. */
		return sprintf( __( 'Webhook: %1$s %2$s', 'glocalsaino-actions-automation-based-on-trigger' ), $action['method'], $host ? $host : $action['url'] );
	}

	public static function describe_email( array $action ) {
		/* translators: %s: recipient. */
		return sprintf( __( 'Email to %s', 'glocalsaino-actions-automation-based-on-trigger' ), $action['to'] );
	}

	/**
	 * Short label of an action result: "Type → target".
	 *
	 * @param array $result Action result.
	 * @return string Plain text.
	 */
	public static function result_label( array $result ) {
		$types = self::types();
		$type  = isset( $types[ $result['type'] ] ) ? $types[ $result['type'] ] : $result['type'];
		return '' !== $result['target'] ? $type . ' → ' . $result['target'] : $type;
	}

	public static function webhook( array $action, array $tokens ) {
		$method = in_array( $action['method'], self::http_methods(), true ) ? $action['method'] : 'POST';
		$url    = esc_url_raw( trim( GSAAT_Engine::replace_tokens( $action['url'], $tokens, 'rawurlencode' ) ), array( 'http', 'https' ) );

		if ( '' === $url ) {
			return self::result( 'webhook', (string) $action['url'], false, __( 'The webhook URL is not valid.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}

		$fields = array();
		foreach ( (array) $action['fields'] as $pair ) {
			$fields[ $pair['key'] ] = GSAAT_Engine::replace_tokens( $pair['value'], $tokens );
		}
		$headers = array();
		foreach ( (array) $action['headers'] as $pair ) {
			$headers[ $pair['key'] ] = GSAAT_Engine::replace_tokens( $pair['value'], $tokens );
		}

		$args = array(
			'method'      => $method,
			'timeout'     => 15,
			'redirection' => 3,
			'headers'     => $headers,
		);

		if ( 'GET' === $method || 'DELETE' === $method ) {
			if ( $fields ) {
				$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $fields, '', '&', PHP_QUERY_RFC3986 );
			}
		} elseif ( 'form' === $action['format'] ) {
			$args['body'] = $fields;
		} else {
			if ( ! self::has_header( $headers, 'Content-Type' ) ) {
				$args['headers']['Content-Type'] = 'application/json; charset=utf-8';
			}
			$args['body'] = wp_json_encode( (object) $fields );
		}

		$target   = $method . ' ' . $url;
		$details  = array( 'sent' => $fields );
		$response = wp_safe_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return self::result(
				'webhook',
				$target,
				false,
				/* translators: %s: error message. */
				sprintf( __( 'Request failed: %s', 'glocalsaino-actions-automation-based-on-trigger' ), $response->get_error_message() ),
				$details
			);
		}

		$code                = (int) wp_remote_retrieve_response_code( $response );
		$details['response'] = self::truncate( (string) wp_remote_retrieve_body( $response ), 2000 );

		return self::result(
			'webhook',
			$target,
			$code >= 200 && $code < 300,
			/* translators: %d: HTTP status code. */
			sprintf( __( 'The external server answered with HTTP status %d.', 'glocalsaino-actions-automation-based-on-trigger' ), $code ),
			$details
		);
	}

	public static function email( array $action, array $tokens ) {
		$to_raw     = GSAAT_Engine::replace_tokens( $action['to'], $tokens );
		$recipients = array();
		foreach ( preg_split( '/[,;]+/', $to_raw ) as $address ) {
			$address = sanitize_email( trim( $address ) );
			if ( is_email( $address ) ) {
				$recipients[] = $address;
			}
		}
		$target = implode( ', ', $recipients );

		if ( ! $recipients ) {
			/* translators: %s: recipient field after replacing the fields. */
			return self::result( 'email', $to_raw, false, sprintf( __( 'No valid recipient address in "%s".', 'glocalsaino-actions-automation-based-on-trigger' ), $to_raw ) );
		}

		$subject = str_replace( array( "\r", "\n" ), ' ', GSAAT_Engine::replace_tokens( $action['subject'], $tokens ) );
		$body    = GSAAT_Engine::replace_tokens( $action['body'], $tokens );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$reply_to = sanitize_email( trim( GSAAT_Engine::replace_tokens( $action['reply_to'], $tokens ) ) );
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$error   = '';
		$capture = static function ( $wp_error ) use ( &$error ) {
			$error = $wp_error->get_error_message();
		};
		add_action( 'wp_mail_failed', $capture );
		$sent = wp_mail( $recipients, $subject, $body, $headers );
		remove_action( 'wp_mail_failed', $capture );

		$details = array( 'subject' => $subject );

		if ( $sent ) {
			return self::result( 'email', $target, true, __( 'Email sent.', 'glocalsaino-actions-automation-based-on-trigger' ), $details );
		}
		return self::result(
			'email',
			$target,
			false,
			'' !== $error
				/* translators: %s: error message. */
				? sprintf( __( 'The email could not be sent: %s', 'glocalsaino-actions-automation-based-on-trigger' ), $error )
				: __( 'The email could not be sent.', 'glocalsaino-actions-automation-based-on-trigger' ),
			$details
		);
	}

	private static function has_header( array $headers, $name ) {
		foreach ( array_keys( $headers ) as $key ) {
			if ( 0 === strcasecmp( $key, $name ) ) {
				return true;
			}
		}
		return false;
	}

	private static function truncate( $text, $length ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_strlen( $text ) > $length ? mb_substr( $text, 0, $length ) . '…' : $text;
		}
		return strlen( $text ) > $length ? substr( $text, 0, $length ) . '…' : $text;
	}

	/**
	 * Build an action result. Add-on actions must return one of these.
	 *
	 * @param string $type    Action type.
	 * @param string $target  What the action acted on (URL, recipient…), shown in the logs.
	 * @param bool   $success Whether it succeeded.
	 * @param string $message Explanation shown in the logs.
	 * @param array  $details Extra data shown in the log detail: sent (pairs), subject, response.
	 * @return array
	 */
	public static function result( $type, $target, $success, $message, array $details = array() ) {
		return array(
			'type'    => $type,
			'target'  => $target,
			'success' => (bool) $success,
			'message' => $message,
			'details' => $details,
		);
	}
}
