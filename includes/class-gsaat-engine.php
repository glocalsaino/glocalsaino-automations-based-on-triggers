<?php
/**
 * Automation engine: firing triggers, conditions, tokens, execution and retries.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Engine {

	/**
	 * Condition operators.
	 *
	 * @return array
	 */
	public static function operators() {
		$operators = array(
			'equals'       => __( 'is equal to', 'glocalsaino-actions-automation-based-on-trigger' ),
			'not_equals'   => __( 'is not equal to', 'glocalsaino-actions-automation-based-on-trigger' ),
			'contains'     => __( 'contains', 'glocalsaino-actions-automation-based-on-trigger' ),
			'not_contains' => __( 'does not contain', 'glocalsaino-actions-automation-based-on-trigger' ),
			'starts_with'  => __( 'starts with', 'glocalsaino-actions-automation-based-on-trigger' ),
			'ends_with'    => __( 'ends with', 'glocalsaino-actions-automation-based-on-trigger' ),
			'is_empty'     => __( 'is empty', 'glocalsaino-actions-automation-based-on-trigger' ),
			'not_empty'    => __( 'is not empty', 'glocalsaino-actions-automation-based-on-trigger' ),
			'greater_than' => __( 'is greater than', 'glocalsaino-actions-automation-based-on-trigger' ),
			'less_than'    => __( 'is less than', 'glocalsaino-actions-automation-based-on-trigger' ),
		);

		/**
		 * Filters the field condition operators. Evaluate new ones with the glocalsaino_aat_compare filter.
		 *
		 * @param array $operators Operator => label.
		 */
		return apply_filters( 'glocalsaino_aat_condition_operators', $operators );
	}

	/**
	 * Operators of the WordPress user conditions.
	 *
	 * @return array
	 */
	public static function user_operators() {
		return array(
			'logged_in'     => __( 'has logged in', 'glocalsaino-actions-automation-based-on-trigger' ),
			'not_logged_in' => __( 'has not logged in', 'glocalsaino-actions-automation-based-on-trigger' ),
			'registered'    => __( 'is a registered user', 'glocalsaino-actions-automation-based-on-trigger' ),
			'is_user'       => __( 'is the user', 'glocalsaino-actions-automation-based-on-trigger' ),
		);
	}

	/**
	 * General tokens available in every automation, keyed by token name.
	 *
	 * @return array
	 */
	public static function builtin_token_labels() {
		return array(
			'_automation_name' => __( 'Automation name', 'glocalsaino-actions-automation-based-on-trigger' ),
			'_date'            => __( 'Date and time of the event', 'glocalsaino-actions-automation-based-on-trigger' ),
			'_site_name'       => __( 'Site name', 'glocalsaino-actions-automation-based-on-trigger' ),
			'_site_url'        => __( 'Site URL', 'glocalsaino-actions-automation-based-on-trigger' ),
		);
	}

	/**
	 * Fire a trigger: run every active automation of that trigger type that matches the event.
	 * This is the entry point for add-on triggers.
	 *
	 * @param string $trigger_type Trigger type slug.
	 * @param array  $data         Event data (field => value; nested arrays are flattened).
	 * @param string $event        Human readable description of the event, shown in the logs.
	 * @param array  $args {
	 *     Optional.
	 *
	 *     @type array    $extra_tokens Additional tokens (names should start with "_").
	 *     @type array    $context      Event context: user_id (logged-in visitor, 0 if none).
	 *     @type callable $matches      Callback( array $automation ): bool deciding whether the event matches the automation's trigger settings.
	 * }
	 * @return array Automation ID => resulting status (see process()).
	 */
	public static function fire( $trigger_type, array $data, $event, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'extra_tokens' => array(),
				'context'      => array(),
				'matches'      => null,
			)
		);
		$data = self::flatten( $data );

		$statuses = array();
		foreach ( GSAAT_Automations::get_active_by_trigger( $trigger_type ) as $automation ) {
			if ( $args['matches'] && ! call_user_func( $args['matches'], $automation ) ) {
				continue;
			}
			/**
			 * Filters whether an event matches an automation of its trigger type.
			 *
			 * @param bool  $matches    Whether it matches.
			 * @param array $automation Automation.
			 * @param array $data       Event data.
			 * @param array $context    Event context.
			 */
			if ( ! apply_filters( 'glocalsaino_aat_trigger_matches', true, $automation, $data, $args['context'] ) ) {
				continue;
			}
			$statuses[ $automation['id'] ] = self::process( $automation, $data, $event, $args['extra_tokens'], $args['context'] );
		}
		return $statuses;
	}

	/**
	 * Run one automation for a trigger event.
	 *
	 * @param array  $automation   Hydrated automation.
	 * @param array  $data         Flattened trigger data (field => string value).
	 * @param string $event        Human readable description of the event.
	 * @param array  $extra_tokens Additional trigger-specific tokens.
	 * @param array  $context      Event context: user_id (logged-in visitor, 0 if none), browser (a browser waits for the response).
	 * @return string|null Resulting status (success|partial|error|skipped) or null if the automation is not available.
	 */
	public static function process( array $automation, array $data, $event, array $extra_tokens = array(), array $context = array() ) {
		if ( 'active' !== GSAAT_Automations::availability( $automation ) ) {
			return null;
		}

		$user_id = isset( $context['user_id'] ) ? (int) $context['user_id'] : 0;
		$tokens  = array_merge(
			$data,
			$extra_tokens,
			array(
				'_automation_name' => $automation['name'],
				'_date'            => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
				'_site_name'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'_site_url'        => home_url( '/' ),
			),
			self::visitor_tokens( $user_id )
		);

		/**
		 * Filters the tokens ({{name}} values) available to the conditions and actions.
		 *
		 * @param array $tokens     Token name => value.
		 * @param array $automation Automation.
		 * @param array $context    Event context.
		 */
		$tokens = apply_filters( 'glocalsaino_aat_tokens', $tokens, $automation, $context );

		$log = array(
			'automation_id'   => $automation['id'],
			'automation_name' => $automation['name'],
			'trigger_type'    => $automation['trigger_type'],
			'event'           => $event,
			'trigger_data'    => $data,
			'context'         => array(
				'tokens'  => array_diff_key( $tokens, $data ),
				'user_id' => $user_id,
			),
		);

		/**
		 * Filters whether the automation conditions are met.
		 *
		 * @param bool  $met        Result of the built-in conditions.
		 * @param array $automation Automation.
		 * @param array $tokens     Tokens.
		 * @param array $context    Event context.
		 */
		$met = apply_filters( 'glocalsaino_aat_conditions_met', self::conditions_met( $automation['conditions'], $tokens, $context ), $automation, $tokens, $context );

		if ( ! $met ) {
			GSAAT_Logs::add(
				$log + array(
					'results' => array(),
					'status'  => 'skipped',
					'message' => __( 'The conditions were not met, no action was executed.', 'glocalsaino-actions-automation-based-on-trigger' ),
				)
			);
			return 'skipped';
		}

		GSAAT_Automations::increment_runs( $automation['id'] );

		$items = array();
		foreach ( array_values( $automation['actions'] ) as $index => $action ) {
			$items[] = array( $index, $action );
		}
		$run     = self::run_sequence( $items, $tokens, $context );
		$results = $run['results'];

		list( $status, $message ) = self::summarize( $results );

		$log_id = GSAAT_Logs::add(
			$log + array(
				'results' => $results,
				'status'  => $status,
				'message' => $message,
			)
		);

		if ( $run['schedule'] ) {
			GSAAT_Scheduler::create( $automation, $run['schedule']['items'], $run['schedule']['run_at'], $run['tokens'], $context, $log_id );
		}

		/**
		 * Fires after an automation ran its actions.
		 *
		 * @param array  $automation Automation.
		 * @param string $status     success|partial|error.
		 * @param array  $results    Action results.
		 * @param int    $log_id     Log entry ID.
		 * @param array  $tokens     Tokens.
		 */
		do_action( 'glocalsaino_aat_automation_executed', $automation, $status, $results, $log_id, $tokens );

		if ( 'success' !== $status ) {
			/**
			 * Fires when some or all actions of an automation failed (not on manual retries).
			 *
			 * @param array  $automation Automation.
			 * @param string $status     partial|error.
			 * @param array  $results    Action results.
			 * @param int    $log_id     Log entry ID.
			 * @param array  $tokens     Tokens.
			 */
			do_action( 'glocalsaino_aat_automation_failed', $automation, $status, $results, $log_id, $tokens );
		}

		return $status;
	}

	/**
	 * Retry the failed actions of a log entry, with the data of the original event.
	 * Successful actions are not repeated and the run counter does not change.
	 *
	 * @param int $log_id Log entry ID.
	 * @return int|WP_Error ID of the new log entry.
	 */
	public static function retry( $log_id ) {
		$log = GSAAT_Logs::get( $log_id );
		if ( ! $log || ! in_array( $log['status'], array( 'error', 'partial' ), true ) ) {
			return new WP_Error( 'glocalsaino_aat_not_retryable', __( 'Only executions with failed actions can be retried.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}
		$automation = GSAAT_Automations::get( $log['automation_id'] );
		if ( ! $automation ) {
			return new WP_Error( 'glocalsaino_aat_automation_deleted', __( 'The automation of this execution no longer exists.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}

		$context = isset( $log['context'] ) ? (array) $log['context'] : array();
		$tokens  = array_merge( $log['trigger_data'], isset( $context['tokens'] ) ? (array) $context['tokens'] : array() );
		$actions = array_values( $automation['actions'] );
		$run_ctx = array( 'user_id' => isset( $context['user_id'] ) ? (int) $context['user_id'] : 0 ); // No browser on a retry.

		// Which current actions correspond to the failed results.
		$targets   = array();
		$unmatched = array();
		foreach ( $log['results'] as $previous ) {
			if ( ! empty( $previous['success'] ) || 'delay' === $previous['type'] ) {
				continue;
			}
			$index = self::find_action( $actions, $previous );
			if ( null === $index ) {
				$unmatched[] = GSAAT_Actions::result(
					$previous['type'],
					$previous['target'],
					false,
					__( 'This action was changed or removed from the automation after the original execution, so it was not retried.', 'glocalsaino-actions-automation-based-on-trigger' )
				);
			} else {
				$targets[ $index ] = true;
			}
		}

		// Walk the actions in order: failed ones run again; pure ones (calculations, formats)
		// are recomputed silently so later actions get their values. Waits are skipped.
		$results = array();
		foreach ( $actions as $index => $action ) {
			$type = isset( $action['type'] ) ? $action['type'] : '';
			if ( 'delay' === $type ) {
				continue;
			}
			$definition = GSAAT_Registry::action_type( $type );
			if ( ! isset( $targets[ $index ] ) && ! ( $definition && $definition['pure'] ) ) {
				continue;
			}
			$result = self::run_action( $action, $index, $tokens, $run_ctx );
			if ( ! empty( $result['success'] ) && ! empty( $result['tokens'] ) ) {
				$tokens = array_merge( $tokens, (array) $result['tokens'] );
			}
			if ( isset( $targets[ $index ] ) ) {
				$results[] = $result;
			}
		}
		$results = array_merge( $results, $unmatched );

		list( $status, $message ) = self::summarize( $results );

		$new_id = GSAAT_Logs::add(
			array(
				'automation_id'   => $automation['id'],
				'automation_name' => $automation['name'],
				'trigger_type'    => $log['trigger_type'],
				/* translators: 1: original log ID, 2: original event. */
				'event'           => sprintf( __( 'Retry of #%1$d: %2$s', 'glocalsaino-actions-automation-based-on-trigger' ), $log['id'], $log['event'] ),
				'trigger_data'    => $log['trigger_data'],
				'context'         => $context,
				'results'         => $results,
				'status'          => $status,
				'message'         => $message,
				'retry_of'        => (int) $log['id'],
			)
		);

		/**
		 * Fires after the failed actions of a log entry were retried.
		 *
		 * @param array  $automation Automation.
		 * @param string $status     success|partial|error.
		 * @param array  $results    Action results.
		 * @param int    $new_id     New log entry ID.
		 * @param int    $log_id     Retried log entry ID.
		 */
		do_action( 'glocalsaino_aat_automation_retried', $automation, $status, $results, $new_id, (int) $log['id'] );

		return $new_id;
	}

	/**
	 * Run actions in order, passing the values produced by each one (calculator, format…) to the
	 * next ones. A "Wait" step stops the run and returns the remaining actions to schedule.
	 *
	 * @param array $items   [ [ index, action ], … ].
	 * @param array $tokens  Tokens.
	 * @param array $context Event context.
	 * @return array { results, tokens, schedule: null|{ items, run_at } }
	 */
	public static function run_sequence( array $items, array $tokens, array $context ) {
		$results = array();
		foreach ( array_values( $items ) as $position => $item ) {
			list( $index, $action ) = $item;

			if ( isset( $action['type'] ) && 'delay' === $action['type'] ) {
				$remaining = array_slice( array_values( $items ), $position + 1 );
				$run_at    = time() + GSAAT_Scheduler::delay_seconds( $action );
				$when      = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $run_at );
				$result    = GSAAT_Actions::result(
					'delay',
					$when,
					true,
					$remaining
						/* translators: 1: number of actions, 2: date. */
						? sprintf( _n( '%1$d action scheduled for %2$s.', '%1$d actions scheduled for %2$s.', count( $remaining ), 'glocalsaino-actions-automation-based-on-trigger' ), count( $remaining ), $when )
						: __( 'There are no actions after this wait.', 'glocalsaino-actions-automation-based-on-trigger' )
				);
				$result['index']       = (int) $index;
				$result['fingerprint'] = self::fingerprint( $action );
				$results[]             = $result;

				return array(
					'results'  => $results,
					'tokens'   => $tokens,
					'schedule' => $remaining ? array(
						'items'  => $remaining,
						'run_at' => $run_at,
					) : null,
				);
			}

			$result = self::run_action( $action, $index, $tokens, $context );
			if ( ! empty( $result['success'] ) && ! empty( $result['tokens'] ) ) {
				$tokens = array_merge( $tokens, (array) $result['tokens'] );
			}
			$results[] = $result;
		}

		return array(
			'results'  => $results,
			'tokens'   => $tokens,
			'schedule' => null,
		);
	}

	/**
	 * Run the actions of a scheduled job (they followed a "Wait" step).
	 *
	 * @param array  $automation Automation.
	 * @param array  $items      [ [ index, action ], … ].
	 * @param array  $tokens     Tokens saved when the wait started.
	 * @param array  $context    Event context (without browser).
	 * @param string $event      Event description for the log.
	 * @param int    $parent_id  Log entry that scheduled the job.
	 * @return int New log entry ID.
	 */
	public static function run_scheduled( array $automation, array $items, array $tokens, array $context, $event, $parent_id ) {
		$run = self::run_sequence( $items, $tokens, $context );

		list( $status, $message ) = self::summarize( $run['results'] );

		$parent = GSAAT_Logs::get( $parent_id );
		$data   = $parent ? $parent['trigger_data'] : array();
		$log_id = GSAAT_Logs::add(
			array(
				'automation_id'   => $automation['id'],
				'automation_name' => $automation['name'],
				'trigger_type'    => $automation['trigger_type'],
				'event'           => $event,
				'trigger_data'    => $data,
				'context'         => array(
					'tokens'  => array_diff_key( $tokens, $data ),
					'user_id' => isset( $context['user_id'] ) ? (int) $context['user_id'] : 0,
				),
				'results'         => $run['results'],
				'status'          => $status,
				'message'         => $message,
				'parent_id'       => (int) $parent_id,
			)
		);

		if ( $run['schedule'] ) {
			GSAAT_Scheduler::create( $automation, $run['schedule']['items'], $run['schedule']['run_at'], $run['tokens'], $context, $log_id );
		}

		/** This action is documented in includes/class-gsaat-engine.php */
		do_action( 'glocalsaino_aat_automation_executed', $automation, $status, $run['results'], $log_id, $run['tokens'] );
		if ( 'success' !== $status ) {
			/** This action is documented in includes/class-gsaat-engine.php */
			do_action( 'glocalsaino_aat_automation_failed', $automation, $status, $run['results'], $log_id, $run['tokens'] );
		}

		return $log_id;
	}

	/**
	 * Execute one action and tag the result so it can be matched again on retry.
	 *
	 * @param array $action  Action.
	 * @param int   $index   Position in the automation.
	 * @param array $tokens  Tokens.
	 * @param array $context Event context.
	 * @return array
	 */
	private static function run_action( array $action, $index, array $tokens, array $context = array() ) {
		$result                = GSAAT_Actions::execute( $action, $tokens, $context );
		$result['index']       = (int) $index;
		$result['fingerprint'] = self::fingerprint( $action );
		return $result;
	}

	/**
	 * Find the current position of the action that produced a result.
	 *
	 * @param array $actions Current actions.
	 * @param array $result  Previous result.
	 * @return int|null
	 */
	private static function find_action( array $actions, array $result ) {
		if ( empty( $result['fingerprint'] ) ) {
			return null;
		}
		if ( isset( $result['index'], $actions[ $result['index'] ] ) && self::fingerprint( $actions[ $result['index'] ] ) === $result['fingerprint'] ) {
			return (int) $result['index'];
		}
		foreach ( $actions as $index => $action ) {
			if ( self::fingerprint( $action ) === $result['fingerprint'] ) {
				return $index;
			}
		}
		return null;
	}

	private static function fingerprint( array $action ) {
		return md5( (string) wp_json_encode( $action ) );
	}

	/**
	 * Overall status of a set of action results.
	 *
	 * @param array $results Action results.
	 * @return array [ status, message ]
	 */
	private static function summarize( array $results ) {
		if ( ! $results ) {
			return array( 'error', __( 'This automation has no actions configured.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}
		$succeeded = count( array_filter( wp_list_pluck( $results, 'success' ) ) );
		if ( count( $results ) === $succeeded ) {
			return array( 'success', '' );
		}
		return array( 0 === $succeeded ? 'error' : 'partial', '' );
	}

	/**
	 * Evaluate the automation conditions against the available tokens.
	 *
	 * @param array $conditions { logic: all|any, rules: array }.
	 * @param array $tokens     Token values.
	 * @param array $context    Event context: user_id (logged-in visitor, 0 if none).
	 * @return bool
	 */
	public static function conditions_met( array $conditions, array $tokens, array $context = array() ) {
		$rules = isset( $conditions['rules'] ) && is_array( $conditions['rules'] ) ? $conditions['rules'] : array();
		if ( ! $rules ) {
			return true;
		}
		$any = isset( $conditions['logic'] ) && 'any' === $conditions['logic'];

		foreach ( $rules as $rule ) {
			if ( isset( $rule['type'] ) && 'user' === $rule['type'] ) {
				$passed = self::user_rule_passes( $rule, $tokens, isset( $context['user_id'] ) ? (int) $context['user_id'] : 0 );
			} else {
				$value  = isset( $tokens[ $rule['field'] ] ) ? (string) $tokens[ $rule['field'] ] : '';
				$passed = self::compare( $value, $rule['operator'], (string) $rule['value'] );
			}
			if ( $any && $passed ) {
				return true;
			}
			if ( ! $any && ! $passed ) {
				return false;
			}
		}

		return ! $any;
	}

	/**
	 * Evaluate a WordPress user condition.
	 *
	 * @param array $rule       { operator, user_field, user_id }.
	 * @param array $tokens     Token values.
	 * @param int   $visitor_id Logged-in visitor ID (0 if none).
	 * @return bool
	 */
	public static function user_rule_passes( array $rule, array $tokens, $visitor_id ) {
		switch ( $rule['operator'] ) {
			case 'logged_in':
				return $visitor_id > 0;
			case 'not_logged_in':
				return 0 === $visitor_id;
			case 'registered':
				return self::resolve_user( $rule, $tokens, $visitor_id ) > 0;
			case 'is_user':
				$user_id = self::resolve_user( $rule, $tokens, $visitor_id );
				return $user_id > 0 && $user_id === (int) $rule['user_id'];
		}
		return false;
	}

	/**
	 * The user a condition refers to: the logged-in visitor, or the user whose email / username is in a field.
	 *
	 * @param array $rule       Rule with optional user_field.
	 * @param array $tokens     Token values.
	 * @param int   $visitor_id Logged-in visitor ID.
	 * @return int User ID, 0 when there is no matching user.
	 */
	private static function resolve_user( array $rule, array $tokens, $visitor_id ) {
		$field = isset( $rule['user_field'] ) ? (string) $rule['user_field'] : '';
		if ( '' === $field ) {
			return $visitor_id;
		}
		$value = isset( $tokens[ $field ] ) ? trim( (string) $tokens[ $field ] ) : '';
		if ( '' === $value ) {
			return 0;
		}
		$user = is_email( $value ) ? get_user_by( 'email', $value ) : get_user_by( 'login', $value );
		return $user ? (int) $user->ID : 0;
	}

	/**
	 * Tokens describing the logged-in visitor (empty when nobody is logged in).
	 *
	 * @param int $user_id Visitor ID.
	 * @return array
	 */
	private static function visitor_tokens( $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : false;
		return array(
			'_user_login'        => $user ? $user->user_login : '',
			'_user_email'        => $user ? $user->user_email : '',
			'_user_display_name' => $user ? $user->display_name : '',
		);
	}

	/**
	 * Compare a value using a condition operator (text comparisons are case-insensitive).
	 *
	 * @param string $value    Actual value.
	 * @param string $operator Operator key.
	 * @param string $expected Expected value.
	 * @return bool
	 */
	public static function compare( $value, $operator, $expected ) {
		$a = self::lower( trim( $value ) );
		$b = self::lower( trim( $expected ) );

		switch ( $operator ) {
			case 'equals':
				return $a === $b;
			case 'not_equals':
				return $a !== $b;
			case 'contains':
				return '' === $b || false !== strpos( $a, $b );
			case 'not_contains':
				return '' !== $b && false === strpos( $a, $b );
			case 'starts_with':
				return 0 === strpos( $a, $b );
			case 'ends_with':
				return '' === $b || substr( $a, -strlen( $b ) ) === $b;
			case 'is_empty':
				return '' === $a;
			case 'not_empty':
				return '' !== $a;
			case 'greater_than':
				return is_numeric( $a ) && is_numeric( $b ) && (float) $a > (float) $b;
			case 'less_than':
				return is_numeric( $a ) && is_numeric( $b ) && (float) $a < (float) $b;
		}

		/**
		 * Evaluates a custom condition operator added with glocalsaino_aat_condition_operators.
		 *
		 * @param bool   $result   Default false.
		 * @param string $operator Operator key.
		 * @param string $value    Actual value.
		 * @param string $expected Expected value.
		 */
		return (bool) apply_filters( 'glocalsaino_aat_compare', false, $operator, $value, $expected );
	}

	/**
	 * Replace {{field}} tokens in a text.
	 *
	 * @param string        $text   Text containing tokens.
	 * @param array         $tokens Token values.
	 * @param callable|null $encode Optional callback applied to each replaced value.
	 * @return string
	 */
	public static function replace_tokens( $text, array $tokens, $encode = null ) {
		return preg_replace_callback(
			'/\{\{\s*([^{}]+?)\s*\}\}/',
			static function ( $match ) use ( $tokens, $encode ) {
				$value = isset( $tokens[ $match[1] ] ) ? (string) $tokens[ $match[1] ] : '';
				return $encode ? call_user_func( $encode, $value ) : $value;
			},
			(string) $text
		);
	}

	/**
	 * Flatten nested data into field => string pairs (nested keys joined with dots, lists joined with commas).
	 *
	 * @param array  $data   Raw data.
	 * @param string $prefix Key prefix.
	 * @return array
	 */
	public static function flatten( array $data, $prefix = '' ) {
		$flat = array();
		foreach ( $data as $key => $value ) {
			$name = '' === $prefix ? (string) $key : $prefix . '.' . $key;
			if ( is_array( $value ) ) {
				$is_scalar_list = array_values( $value ) === $value && count( array_filter( $value, 'is_scalar' ) ) === count( $value );
				if ( $is_scalar_list ) {
					$flat[ $name ] = implode( ', ', array_map( 'strval', $value ) );
				} else {
					$flat += self::flatten( $value, $name );
				}
			} elseif ( is_bool( $value ) ) {
				$flat[ $name ] = $value ? 'true' : 'false';
			} elseif ( is_scalar( $value ) || null === $value ) {
				$flat[ $name ] = (string) $value;
			}
		}
		return $flat;
	}

	/**
	 * Field / key names: letters, digits and _ . - [ ] :
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public static function sanitize_field_name( $name ) {
		return substr( preg_replace( '/[^A-Za-z0-9_.\-\[\]:]/', '', (string) $name ), 0, 100 );
	}

	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}
}
