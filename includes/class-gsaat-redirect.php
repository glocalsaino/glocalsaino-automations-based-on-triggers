<?php
/**
 * "Redirect the user" action: holds the redirect requested during the current request
 * and hands it to whatever sends the visitor's browser somewhere next.
 *
 * Consumers: link and button triggers (they redirect themselves), Contact Form 7
 * (the URL travels in the form's AJAX response and a small script follows it),
 * and the login / registration screens (login_redirect / registration_redirect).
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Redirect {

	/**
	 * Redirect URL requested during this request.
	 *
	 * @var string
	 */
	private static $pending = '';

	public static function init() {
		add_filter( 'wpcf7_feedback_response', array( __CLASS__, 'cf7_response' ), 10, 1 );
		add_action( 'wpcf7_enqueue_scripts', array( __CLASS__, 'cf7_script' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 99, 1 );
		add_filter( 'registration_redirect', array( __CLASS__, 'login_redirect' ), 99, 1 );
	}

	public static function set( $url ) {
		self::$pending = (string) $url;
	}

	/**
	 * Return and clear the pending redirect.
	 *
	 * @return string Empty when there is none.
	 */
	public static function take() {
		$url           = self::$pending;
		self::$pending = '';
		return $url;
	}

	/**
	 * Redirect response for a REST route that is opened directly by the browser (links, forms).
	 *
	 * @param string $url Destination (set by an administrator or validated by build_url()).
	 * @return WP_REST_Response
	 */
	public static function rest_redirect( $url ) {
		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', esc_url_raw( $url ) );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	public static function cf7_response( $response ) {
		$url = self::take();
		if ( '' !== $url && is_array( $response ) ) {
			$response['glocalsaino_aat_redirect'] = $url;
		}
		return $response;
	}

	public static function cf7_script() {
		wp_enqueue_script( 'glocalsaino-aat-cf7-redirect', GSAAT_URL . 'assets/js/cf7-redirect.js', array(), GSAAT_VERSION, true );
	}

	public static function login_redirect( $redirect_to ) {
		$url = self::take();
		return '' !== $url ? $url : $redirect_to;
	}

	/**
	 * Final redirect URL of a "Redirect" action, or an error when it would send the visitor to a
	 * host that is neither this site nor the host written literally in the action URL (a field
	 * value must never be able to choose the destination site).
	 *
	 * @param string $template URL with tokens.
	 * @param array  $tokens   Token values.
	 * @return string|WP_Error
	 */
	public static function build_url( $template, array $tokens ) {
		$template = trim( (string) $template );
		$url      = esc_url_raw( GSAAT_Engine::replace_tokens( $template, $tokens, 'rawurlencode' ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			return new WP_Error( 'glocalsaino_aat_bad_url', __( 'The redirect URL is not valid.', 'glocalsaino-automations-based-on-triggers' ) );
		}

		$host    = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$allowed = array( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		$literal = preg_replace( '/\{\{[^{}]*\}\}/', '', $template );
		if ( preg_match( '#^https?://([^/?\#{]+)#i', $literal, $match ) && $match[0] === substr( $template, 0, strlen( $match[0] ) ) ) {
			$allowed[] = strtolower( preg_replace( '/:\d+$/', '', $match[1] ) );
		}

		if ( '' !== $host && ! in_array( $host, $allowed, true ) ) {
			return new WP_Error( 'glocalsaino_aat_bad_host', __( 'The redirect was blocked: the destination site cannot come from a field value.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		return $url;
	}
}
