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
		return array(
			'webhook' => __( 'Send data to an external webhook', 'glocalsaino-actions-automation-based-on-trigger' ),
			'email'   => __( 'Send an email', 'glocalsaino-actions-automation-based-on-trigger' ),
		);
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
	 * Execute one action.
	 *
	 * @param array $action Action configuration.
	 * @param array $tokens Token values.
	 * @return array { type, target, success, message, details }
	 */
	public static function execute( array $action, array $tokens ) {
		$type = isset( $action['type'] ) ? $action['type'] : '';
		if ( 'webhook' === $type ) {
			return self::webhook( $action, $tokens );
		}
		if ( 'email' === $type ) {
			return self::email( $action, $tokens );
		}
		return self::result( $type, '', false, __( 'Unknown action type.', 'glocalsaino-actions-automation-based-on-trigger' ) );
	}

	private static function webhook( array $action, array $tokens ) {
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

	private static function email( array $action, array $tokens ) {
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

	private static function result( $type, $target, $success, $message, array $details = array() ) {
		return array(
			'type'    => $type,
			'target'  => $target,
			'success' => (bool) $success,
			'message' => $message,
			'details' => $details,
		);
	}
}
