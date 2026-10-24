<?php
/**
 * Trigger: WordPress user events (registration, login, password change).
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Trigger_User {

	/**
	 * Users being created in this request (WordPress fires wp_set_password for them too).
	 *
	 * @var bool
	 */
	private static $creating = false;

	/**
	 * Users whose password change was already handled in this request.
	 *
	 * @var int[]
	 */
	private static $password_done = array();

	public static function init() {
		add_filter( 'wp_pre_insert_user_data', array( __CLASS__, 'mark_creation' ), 10, 2 );
		add_action( 'user_register', array( __CLASS__, 'on_register' ), 20, 1 );
		add_action( 'wp_login', array( __CLASS__, 'on_login' ), 20, 2 );
		add_action( 'wp_set_password', array( __CLASS__, 'on_set_password' ), 20, 2 );
		add_action( 'profile_update', array( __CLASS__, 'on_profile_update' ), 20, 2 );
	}

	/**
	 * Events that can be chosen.
	 *
	 * @return array
	 */
	public static function events() {
		return array(
			'registered'       => __( 'A user registers', 'glocalsaino-automations-based-on-triggers' ),
			'logged_in'        => __( 'A user logs in', 'glocalsaino-automations-based-on-triggers' ),
			'password_changed' => __( 'A user changes their password', 'glocalsaino-automations-based-on-triggers' ),
		);
	}

	/**
	 * Fields provided by this trigger.
	 *
	 * @return string[]
	 */
	public static function fields() {
		return array( 'user_id', 'user_login', 'user_email', 'display_name', 'first_name', 'last_name', 'roles', 'registered', 'event' );
	}

	public static function mark_creation( $data, $update ) {
		if ( ! $update ) {
			self::$creating = true;
		}
		return $data;
	}

	public static function on_register( $user_id ) {
		self::$creating = false;
		self::fire( 'registered', (int) $user_id );
	}

	public static function on_login( $user_login, $user ) {
		self::fire( 'logged_in', $user instanceof WP_User ? (int) $user->ID : 0 );
	}

	public static function on_set_password( $password, $user_id ) {
		if ( self::$creating ) {
			return;
		}
		self::password_changed( (int) $user_id );
	}

	/**
	 * WordPress versions before 6.7 do not fire wp_set_password when a password is changed
	 * from the profile screen, so compare the stored hashes too.
	 *
	 * @param int     $user_id       User ID.
	 * @param WP_User $old_user_data User before the update.
	 */
	public static function on_profile_update( $user_id, $old_user_data ) {
		$user = get_userdata( $user_id );
		if ( $user && $old_user_data instanceof WP_User && $user->user_pass !== $old_user_data->user_pass ) {
			self::password_changed( (int) $user_id );
		}
	}

	private static function password_changed( $user_id ) {
		if ( in_array( $user_id, self::$password_done, true ) ) {
			return;
		}
		self::$password_done[] = $user_id;
		self::fire( 'password_changed', $user_id );
	}

	private static function fire( $event, $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user ) {
			return;
		}

		$events = self::events();
		$data   = array(
			'user_id'      => (string) $user->ID,
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => (string) get_user_meta( $user->ID, 'first_name', true ),
			'last_name'    => (string) get_user_meta( $user->ID, 'last_name', true ),
			'roles'        => implode( ', ', (array) $user->roles ),
			'registered'   => $user->user_registered,
			'event'        => $event,
		);

		GSAAT_Engine::fire(
			'wp_user',
			$data,
			/* translators: 1: event, 2: user login. */
			sprintf( __( '%1$s: %2$s', 'glocalsaino-automations-based-on-triggers' ), $events[ $event ], $user->user_login ),
			array(
				'context' => array(
					'user_id' => (int) $user->ID,
					'browser' => ! wp_doing_cron() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! ( defined( 'WP_CLI' ) && WP_CLI ),
				),
				'matches' => static function ( $automation ) use ( $event ) {
					$config = self::config( $automation['trigger_config'] );
					return in_array( $event, $config['events'], true );
				},
			)
		);
	}

	public static function config( array $config ) {
		return wp_parse_args( $config, array( 'events' => array() ) );
	}

	public static function sanitize_config( array $post, array &$notices ) {
		$chosen = isset( $post['user_events'] ) && is_array( $post['user_events'] ) ? array_map( 'sanitize_key', $post['user_events'] ) : array();
		$config = array( 'events' => array_values( array_intersect( array_keys( self::events() ), $chosen ) ) );
		if ( ! $config['events'] ) {
			$config['_incomplete'] = true;
		}
		return $config;
	}

	public static function describe( array $automation ) {
		$config = self::config( $automation['trigger_config'] );
		$labels = array_intersect_key( self::events(), array_flip( $config['events'] ) );
		return implode( ' / ', $labels );
	}
}
