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
	 * @param array $action  Action configuration.
	 * @param array $tokens  Token values.
	 * @param array $context Event context: user_id, browser (a browser waits for the response).
	 * @return array { type, target, success, message, details, tokens? }
	 */
	public static function execute( array $action, array $tokens, array $context = array() ) {
		$type       = isset( $action['type'] ) ? (string) $action['type'] : '';
		$definition = GSAAT_Registry::action_type( $type );

		if ( ! $definition || ! is_callable( $definition['execute'] ) ) {
			return self::result( $type, '', false, __( 'This action type is not available. If it comes from an add-on, check that the add-on is active.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}

		$result = call_user_func( $definition['execute'], $action, $tokens, $context );
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

	/*
	 * ---------------------------------------------------------------------
	 * Calculator
	 * ---------------------------------------------------------------------
	 */

	public static function calculator( array $action, array $tokens ) {
		$output = $action['output'];
		$value  = GSAAT_Calculator::evaluate_with_tokens( $action['expression'], $tokens );
		if ( is_wp_error( $value ) ) {
			/* translators: %s: error message. */
			return self::result( 'calculator', '{{' . $output . '}}', false, sprintf( __( 'The calculation failed: %s', 'glocalsaino-actions-automation-based-on-trigger' ), $value->get_error_message() ) );
		}
		$text = GSAAT_Calculator::number_to_string( $value, '' === $action['decimals'] ? null : (int) $action['decimals'] );
		$result           = self::result( 'calculator', '{{' . $output . '}}', true, '= ' . $text );
		$result['tokens'] = array( $output => $text );
		return $result;
	}

	public static function sanitize_calculator( array $action ) {
		$decimals = isset( $action['decimals'] ) ? trim( (string) $action['decimals'] ) : '';
		return array(
			'expression' => isset( $action['expression'] ) ? sanitize_text_field( (string) $action['expression'] ) : '',
			'decimals'   => '' === $decimals ? '' : (string) min( 10, absint( $decimals ) ),
			'output'     => self::sanitize_output( isset( $action['output'] ) ? $action['output'] : '', 'result' ),
		);
	}

	public static function describe_calculator( array $action ) {
		/* translators: 1: output field, 2: expression. */
		return sprintf( __( 'Calculate {{%1$s}} = %2$s', 'glocalsaino-actions-automation-based-on-trigger' ), $action['output'], $action['expression'] );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Format data
	 * ---------------------------------------------------------------------
	 */

	public static function format( array $action, array $tokens ) {
		$output = $action['output'];
		$input  = GSAAT_Engine::replace_tokens( $action['input'], $tokens );
		$value  = GSAAT_Formatter::apply( $action['operation'], $input, (array) $action['params'] );
		if ( is_wp_error( $value ) ) {
			return self::result( 'format', '{{' . $output . '}}', false, $value->get_error_message() );
		}
		$result           = self::result( 'format', '{{' . $output . '}}', true, '"' . $input . '" → "' . $value . '"' );
		$result['tokens'] = array( $output => $value );
		return $result;
	}

	public static function sanitize_format( array $action ) {
		$operation = isset( $action['operation'] ) ? (string) $action['operation'] : '';
		$params    = isset( $action['params'] ) && is_array( $action['params'] ) ? array_values( $action['params'] ) : array();
		$clean     = array();
		for ( $i = 0; $i < 3; $i++ ) {
			// Keep spaces: separators such as " " are meaningful here.
			$clean[] = isset( $params[ $i ] ) && is_scalar( $params[ $i ] ) ? wp_strip_all_tags( (string) $params[ $i ] ) : '';
		}
		return array(
			'input'     => isset( $action['input'] ) ? sanitize_text_field( (string) $action['input'] ) : '',
			'operation' => array_key_exists( $operation, GSAAT_Formatter::operations() ) ? $operation : 'first_word',
			'params'    => $clean,
			'output'    => self::sanitize_output( isset( $action['output'] ) ? $action['output'] : '', 'formatted' ),
		);
	}

	public static function describe_format( array $action ) {
		$operations = GSAAT_Formatter::operations();
		$label      = isset( $operations[ $action['operation'] ] ) ? $operations[ $action['operation'] ][0] : $action['operation'];
		/* translators: 1: output field, 2: operation, 3: input. */
		return sprintf( __( 'Format {{%1$s}} = %2$s of %3$s', 'glocalsaino-actions-automation-based-on-trigger' ), $action['output'], $label, $action['input'] );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Redirect
	 * ---------------------------------------------------------------------
	 */

	public static function redirect( array $action, array $tokens, array $context = array() ) {
		if ( empty( $context['browser'] ) ) {
			return self::result( 'redirect', (string) $action['url'], false, __( 'Nobody can be redirected: this trigger, a wait or a retry does not run in the visitor\'s browser.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}
		$url = GSAAT_Redirect::build_url( $action['url'], $tokens );
		if ( is_wp_error( $url ) ) {
			return self::result( 'redirect', (string) $action['url'], false, $url->get_error_message() );
		}
		GSAAT_Redirect::set( $url );
		return self::result( 'redirect', $url, true, __( 'The visitor is redirected to this address.', 'glocalsaino-actions-automation-based-on-trigger' ) );
	}

	public static function sanitize_redirect( array $action ) {
		return array( 'url' => isset( $action['url'] ) ? sanitize_text_field( (string) $action['url'] ) : '' );
	}

	public static function describe_redirect( array $action ) {
		/* translators: %s: URL. */
		return sprintf( __( 'Redirect to %s', 'glocalsaino-actions-automation-based-on-trigger' ), $action['url'] );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Add rows to a table
	 * ---------------------------------------------------------------------
	 */

	/**
	 * One row per event; with a file trigger in "once per file" mode, one row per file row
	 * (the row's columns are available as fields).
	 *
	 * @param array $action  { table_id, mode: add|replace, mapping: column => template }.
	 * @param array $tokens  Tokens.
	 * @param array $context Event context (rows of a file).
	 * @return array
	 */
	public static function table( array $action, array $tokens, array $context = array() ) {
		$table = GSAAT_Tables::get( (int) $action['table_id'] );
		if ( ! $table ) {
			return self::result( 'table', '#' . (int) $action['table_id'], false, __( 'The table does not exist (it may have been deleted).', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}

		if ( ! empty( $context['rows_omitted'] ) ) {
			return self::result( 'table', $table['name'], false, __( 'The file had too many rows to keep them for a retry. Upload the file again.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}

		$mapping = (array) $action['mapping'];
		$build   = static function ( array $row_tokens ) use ( $table, $mapping ) {
			$row = array();
			foreach ( $table['columns'] as $column ) {
				$template              = isset( $mapping[ $column['key'] ] ) ? (string) $mapping[ $column['key'] ] : '';
				$row[ $column['key'] ] = GSAAT_Engine::replace_tokens( $template, $row_tokens );
			}
			return $row;
		};

		$rows = array();
		if ( isset( $context['rows'] ) && is_array( $context['rows'] ) ) {
			foreach ( $context['rows'] as $file_row ) {
				$rows[] = $build( array_merge( $tokens, GSAAT_Engine::flatten( (array) $file_row ) ) );
			}
		} else {
			$rows[] = $build( $tokens );
		}

		$added = GSAAT_Tables::add_rows(
			$table['id'],
			$rows,
			array( 'automation_id' => isset( $context['automation_id'] ) ? (int) $context['automation_id'] : 0 ),
			'replace' === $action['mode']
		);
		if ( is_wp_error( $added ) ) {
			return self::result( 'table', $table['name'], false, $added->get_error_message() );
		}

		return self::result(
			'table',
			$table['name'],
			true,
			'replace' === $action['mode']
				/* translators: %d: number of rows. */
				? sprintf( _n( 'The table now has %d row (previous rows replaced).', 'The table now has %d rows (previous rows replaced).', $added, 'glocalsaino-actions-automation-based-on-trigger' ), $added )
				/* translators: %d: number of rows. */
				: sprintf( _n( '%d row added.', '%d rows added.', $added, 'glocalsaino-actions-automation-based-on-trigger' ), $added ),
			$rows ? array( 'sent' => $rows[0] ) : array()
		);
	}

	public static function sanitize_table( array $action ) {
		$mapping = array();
		foreach ( isset( $action['mapping'] ) && is_array( $action['mapping'] ) ? $action['mapping'] : array() as $key => $template ) {
			$key = sanitize_key( (string) $key );
			if ( '' !== $key && is_scalar( $template ) ) {
				$mapping[ $key ] = sanitize_text_field( (string) $template );
			}
		}
		return array(
			'table_id' => isset( $action['table_id'] ) ? absint( $action['table_id'] ) : 0,
			'mode'     => ( isset( $action['mode'] ) && 'replace' === $action['mode'] ) ? 'replace' : 'add',
			'mapping'  => $mapping,
		);
	}

	public static function describe_table( array $action ) {
		$table = GSAAT_Tables::get( (int) $action['table_id'] );
		/* translators: %s: table name. */
		return sprintf( __( 'Add rows to the table "%s"', 'glocalsaino-actions-automation-based-on-trigger' ), $table ? $table['name'] : '#' . (int) $action['table_id'] );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Wait
	 * ---------------------------------------------------------------------
	 */

	public static function sanitize_delay( array $action ) {
		$unit = isset( $action['unit'] ) ? (string) $action['unit'] : 'days';
		return array(
			'amount' => max( 1, min( 10000, isset( $action['amount'] ) ? absint( $action['amount'] ) : 1 ) ),
			'unit'   => array_key_exists( $unit, GSAAT_Scheduler::units() ) ? $unit : 'days',
		);
	}

	public static function describe_delay( array $action ) {
		$units = GSAAT_Scheduler::units();
		/* translators: 1: amount, 2: unit. */
		return sprintf( __( 'Wait %1$d %2$s', 'glocalsaino-actions-automation-based-on-trigger' ), $action['amount'], $units[ $action['unit'] ][0] );
	}

	/**
	 * Name of the value produced by an action (letters, digits, _ . -).
	 *
	 * @param mixed  $name    Raw name.
	 * @param string $default Fallback.
	 * @return string
	 */
	private static function sanitize_output( $name, $default ) {
		$name = ltrim( GSAAT_Engine::sanitize_field_name( (string) $name ), '_' );
		return '' !== $name ? $name : $default;
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
