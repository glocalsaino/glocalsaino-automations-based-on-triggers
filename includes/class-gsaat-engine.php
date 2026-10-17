<?php
/**
 * Automation engine: availability, conditions, tokens and execution.
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
		return array(
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
	 * Run an automation for a trigger event.
	 *
	 * @param array  $automation   Hydrated automation.
	 * @param array  $data         Flattened trigger data (field => string value).
	 * @param string $event        Human readable description of the event.
	 * @param array  $extra_tokens Additional trigger-specific tokens.
	 * @param array  $context      Event context: user_id (logged-in visitor, 0 if none).
	 * @return string|null Resulting status (success|partial|error|skipped) or null if the automation is not available.
	 */
	public static function process( array $automation, array $data, $event, array $extra_tokens = array(), array $context = array() ) {
		if ( 'active' !== GSAAT_Automations::availability( $automation ) ) {
			return null;
		}

		$tokens = array_merge(
			$data,
			$extra_tokens,
			array(
				'_automation_name' => $automation['name'],
				'_date'            => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
				'_site_name'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'_site_url'        => home_url( '/' ),
			),
			self::visitor_tokens( isset( $context['user_id'] ) ? (int) $context['user_id'] : 0 )
		);

		$log = array(
			'automation_id'   => $automation['id'],
			'automation_name' => $automation['name'],
			'trigger_type'    => $automation['trigger_type'],
			'event'           => $event,
			'trigger_data'    => $data,
		);

		if ( ! self::conditions_met( $automation['conditions'], $tokens, $context ) ) {
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

		$results = array();
		foreach ( $automation['actions'] as $action ) {
			$results[] = GSAAT_Actions::execute( $action, $tokens );
		}

		$succeeded = count( array_filter( wp_list_pluck( $results, 'success' ) ) );
		if ( ! $results ) {
			$status  = 'error';
			$message = __( 'This automation has no actions configured.', 'glocalsaino-actions-automation-based-on-trigger' );
		} elseif ( count( $results ) === $succeeded ) {
			$status  = 'success';
			$message = '';
		} elseif ( 0 === $succeeded ) {
			$status  = 'error';
			$message = '';
		} else {
			$status  = 'partial';
			$message = '';
		}

		GSAAT_Logs::add(
			$log + array(
				'results' => $results,
				'status'  => $status,
				'message' => $message,
			)
		);

		return $status;
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
		return false;
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

	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}
}
