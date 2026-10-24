<?php
/**
 * Trigger: embeddable widget (button, link or form) shown on third-party websites.
 *
 * The third party pastes an <iframe> (or a one-line loader script that creates it and
 * adjusts its height). The iframe loads a minimal page served by this site; when a
 * visitor clicks or sends, their browser posts to this site, the submission is checked
 * and the automation runs.
 *
 * Protection: allowed websites (Content-Security-Policy frame-ancestors + check of the
 * embedding site), single-use signed token issued when the widget loads, minimum time
 * to send, honeypot field, per-IP rate limit, optional captcha and server-side
 * validation of the configured fields only.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Trigger_Widget {

	const PAGE_ARG       = 'glocalsaino_aat_widget';
	const TOKEN_LIFETIME = 7200;
	const MIN_SECONDS    = 2;
	const HONEYPOT       = 'gsaat_hp';
	const RESERVED       = array( 'token', 'hp', 'captcha', 'page', self::HONEYPOT );

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 0 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Configuration
	 * ---------------------------------------------------------------------
	 */

	public static function kinds() {
		return array(
			'form'   => __( 'Form with fields', 'glocalsaino-actions-automation-based-on-trigger' ),
			'button' => __( 'Button', 'glocalsaino-actions-automation-based-on-trigger' ),
			'link'   => __( 'Link', 'glocalsaino-actions-automation-based-on-trigger' ),
		);
	}

	public static function field_types() {
		return array(
			'text'     => __( 'Text', 'glocalsaino-actions-automation-based-on-trigger' ),
			'email'    => __( 'Email', 'glocalsaino-actions-automation-based-on-trigger' ),
			'number'   => __( 'Number', 'glocalsaino-actions-automation-based-on-trigger' ),
			'tel'      => __( 'Phone', 'glocalsaino-actions-automation-based-on-trigger' ),
			'textarea' => __( 'Long text', 'glocalsaino-actions-automation-based-on-trigger' ),
			'select'   => __( 'Drop-down list', 'glocalsaino-actions-automation-based-on-trigger' ),
			'checkbox' => __( 'Checkbox', 'glocalsaino-actions-automation-based-on-trigger' ),
			'consent'  => __( 'Privacy consent', 'glocalsaino-actions-automation-based-on-trigger' ),
		);
	}

	public static function fonts() {
		return array(
			'system' => array( __( 'System (like the visitor\'s device)', 'glocalsaino-actions-automation-based-on-trigger' ), '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif' ),
			'sans'   => array( __( 'Sans-serif', 'glocalsaino-actions-automation-based-on-trigger' ), 'Helvetica, Arial, sans-serif' ),
			'serif'  => array( __( 'Serif', 'glocalsaino-actions-automation-based-on-trigger' ), 'Georgia, "Times New Roman", serif' ),
			'mono'   => array( __( 'Monospace', 'glocalsaino-actions-automation-based-on-trigger' ), 'Menlo, Consolas, monospace' ),
		);
	}

	public static function config( array $config ) {
		$config           = wp_parse_args(
			$config,
			array(
				'kind'        => 'form',
				'title'       => '',
				'intro'       => '',
				'text'        => __( 'Send', 'glocalsaino-actions-automation-based-on-trigger' ),
				'destination' => '',
				'fields'      => array(),
				'message'     => __( 'Thank you!', 'glocalsaino-actions-automation-based-on-trigger' ),
				'redirect'    => '',
				'design'      => array(),
				'domains'     => array(),
				'rate_limit'  => 10,
				'captcha'     => '',
			)
		);
		$config['design'] = wp_parse_args(
			(array) $config['design'],
			array(
				'primary'    => '#2271b1',
				'text'       => '#1d2327',
				'background' => '#ffffff',
				'radius'     => 6,
				'font'       => 'system',
				'site_css'   => false,
			)
		);
		return $config;
	}

	/**
	 * URL of the widget page (what the iframe loads).
	 *
	 * @param string $key Automation public key.
	 * @return string
	 */
	public static function widget_url( $key ) {
		return add_query_arg( self::PAGE_ARG, $key, home_url( '/' ) );
	}

	public static function loader_url() {
		return GSAAT_URL . 'assets/js/widget-loader.js';
	}

	/**
	 * Plain iframe code the third party pastes (the loader script snippet is built by the editor script).
	 *
	 *
	 * @param array $automation Automation.
	 * @return string
	 */
	public static function iframe_code( array $automation ) {
		return sprintf(
			'<iframe src="%1$s" title="%2$s" loading="lazy" style="width:100%%;border:0;min-height:120px" height="320"></iframe>',
			esc_url( self::widget_url( $automation['webhook_key'] ) ),
			esc_attr( $automation['name'] )
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Widget page (inside the iframe)
	 * ---------------------------------------------------------------------
	 */

	public static function maybe_render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public widget page.
		if ( ! isset( $_GET[ self::PAGE_ARG ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public widget page.
		$key        = preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_GET[ self::PAGE_ARG ] ) ) );
		$automation = '' !== $key ? GSAAT_Automations::get_by_webhook_key( $key ) : null;

		nocache_headers();
		if ( ! $automation || 'widget' !== $automation['trigger_type'] ) {
			status_header( 404 );
			exit;
		}

		$config    = self::config( $automation['trigger_config'] );
		$available = 'active' === GSAAT_Automations::availability( $automation );

		self::send_frame_headers( $config['domains'] );
		status_header( 200 );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

		wp_register_style( 'glocalsaino-aat-widget', GSAAT_URL . 'assets/css/widget.css', array(), GSAAT_VERSION );
		wp_add_inline_style( 'glocalsaino-aat-widget', self::design_css( $config['design'] ) );

		$dependencies = array();
		$captcha      = null;
		if ( $available && '' !== $config['captcha'] ) {
			$providers = GSAAT_Captcha::providers();
			if ( isset( $providers[ $config['captcha'] ] ) && GSAAT_Captcha::configured( $config['captcha'] ) ) {
				$keys    = GSAAT_Captcha::keys( $config['captcha'] );
				$captcha = array(
					'provider' => $config['captcha'],
					'siteKey'  => $keys['site'],
				);
				// The provider's own script; it is only loaded on widgets that use a captcha.
				wp_register_script( 'glocalsaino-aat-captcha', $providers[ $config['captcha'] ]['script'], array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External service script, versioned by the provider.
				$dependencies[] = 'glocalsaino-aat-captcha';
			}
		}

		wp_register_script( 'glocalsaino-aat-widget', GSAAT_URL . 'assets/js/widget.js', $dependencies, GSAAT_VERSION, true );
		wp_add_inline_script(
			'glocalsaino-aat-widget',
			'window.glocalsainoAatWidget = ' . wp_json_encode(
				array(
					'tokenUrl'  => rest_url( GSAAT_Automations::REST_NAMESPACE . '/widget/' . $key . '/token' ),
					'submitUrl' => rest_url( GSAAT_Automations::REST_NAMESPACE . '/widget/' . $key ),
					'kind'      => $config['kind'],
					'captcha'   => $captcha,
					'i18n'      => array(
						'sending'  => __( 'Sending…', 'glocalsaino-actions-automation-based-on-trigger' ),
						'error'    => __( 'Something went wrong. Please try again.', 'glocalsaino-actions-automation-based-on-trigger' ),
						'continue' => __( 'Continue', 'glocalsaino-actions-automation-based-on-trigger' ),
						'captcha'  => __( 'Please complete the verification.', 'glocalsaino-actions-automation-based-on-trigger' ),
					),
				)
			) . ';',
			'before'
		);

		$glocalsaino_aat_widget = array(
			'automation' => $automation,
			'config'     => $config,
			'available'  => $available,
			'captcha'    => $captcha,
		);
		include GSAAT_PATH . 'includes/views/widget-page.php';
		exit;
	}

	/**
	 * Allow embedding only on the configured websites (and this site, for the preview).
	 *
	 * @param string[] $domains Allowed hosts, "*.example.com" for subdomains.
	 */
	private static function send_frame_headers( array $domains ) {
		header_remove( 'X-Frame-Options' );
		$sources = array( "'self'" );
		foreach ( $domains as $domain ) {
			$sources[] = 'https://' . $domain;
			$sources[] = 'http://' . $domain;
		}
		header( 'Content-Security-Policy: frame-ancestors ' . ( $domains ? implode( ' ', $sources ) : '*' ) );
	}

	/**
	 * CSS variables of the widget design, plus (optionally) this site's Additional CSS.
	 *
	 * @param array $design Design settings.
	 * @return string
	 */
	private static function design_css( array $design ) {
		$fonts = self::fonts();
		$font  = isset( $fonts[ $design['font'] ] ) ? $fonts[ $design['font'] ][1] : $fonts['system'][1];
		$css   = sprintf(
			':root{--gsaat-primary:%1$s;--gsaat-text:%2$s;--gsaat-background:%3$s;--gsaat-radius:%4$dpx;--gsaat-font:%5$s;}',
			self::color( $design['primary'], '#2271b1' ),
			self::color( $design['text'], '#1d2327' ),
			self::color( $design['background'], '#ffffff' ),
			max( 0, min( 40, (int) $design['radius'] ) ),
			$font
		);
		return ! empty( $design['site_css'] ) ? $css . "\n" . self::site_additional_css() : $css;
	}

	/**
	 * The site's own Additional CSS, written in WordPress itself (Customizer, or Site Editor →
	 * Styles for block themes). The plugin has no CSS field of its own.
	 *
	 * @return string
	 */
	private static function site_additional_css() {
		$css = function_exists( 'wp_get_custom_css' ) ? wp_get_custom_css() : '';
		if ( function_exists( 'wp_theme_has_theme_json' ) && wp_theme_has_theme_json() && function_exists( 'wp_get_global_stylesheet' ) ) {
			$css .= "\n" . wp_get_global_stylesheet( array( 'custom-css' ) );
		}
		return wp_strip_all_tags( $css );
	}

	private static function color( $value, $default ) {
		$color = sanitize_hex_color( (string) $value );
		return $color ? $color : $default;
	}

	/*
	 * ---------------------------------------------------------------------
	 * REST endpoints
	 * ---------------------------------------------------------------------
	 */

	public static function register_routes() {
		register_rest_route(
			GSAAT_Automations::REST_NAMESPACE,
			'/widget/(?P<key>[A-Za-z0-9]{20,64})/token',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_token' ),
				// Public by design: the widget is shown to anonymous visitors on other websites.
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			GSAAT_Automations::REST_NAMESPACE,
			'/widget/(?P<key>[A-Za-z0-9]{20,64})',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_submit' ),
				// Public by design; protected by the signed token, rate limit, honeypot and optional captcha.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Issue a single-use token when the widget loads (and count the view).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_token( WP_REST_Request $request ) {
		$automation = self::automation_from_request( $request );
		if ( ! $automation ) {
			return new WP_Error( 'glocalsaino_aat_not_found', __( 'Unknown widget.', 'glocalsaino-actions-automation-based-on-trigger' ), array( 'status' => 404 ) );
		}
		if ( 'active' !== GSAAT_Automations::availability( $automation ) ) {
			return new WP_Error( 'glocalsaino_aat_widget_unavailable', __( 'This form is not available right now.', 'glocalsaino-actions-automation-based-on-trigger' ), array( 'status' => 403 ) );
		}

		$config = self::config( $automation['trigger_config'] );
		$host   = self::host_of( (string) $request->get_param( 'parent' ) );
		if ( $config['domains'] && '' !== $host && ! self::host_allowed( $host, $config['domains'] ) ) {
			GSAAT_Widget_Stats::bump( $automation['id'], $host, 'rejected' );
			return new WP_Error( 'glocalsaino_aat_widget_domain', __( 'This website is not allowed to show this form.', 'glocalsaino-actions-automation-based-on-trigger' ), array( 'status' => 403 ) );
		}

		GSAAT_Widget_Stats::bump( $automation['id'], $host, 'views' );
		$response = new WP_REST_Response( array( 'token' => self::make_token( $automation['id'], $host ) ), 200 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Receive a click / form submission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_submit( WP_REST_Request $request ) {
		$automation = self::automation_from_request( $request );
		if ( ! $automation ) {
			return new WP_Error( 'glocalsaino_aat_not_found', __( 'Unknown widget.', 'glocalsaino-actions-automation-based-on-trigger' ), array( 'status' => 404 ) );
		}

		$config = self::config( $automation['trigger_config'] );
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : (array) $request->get_body_params();
		$input  = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();
		$ip     = GSAAT_Webhook_Security::client_ip();
		$page   = isset( $params['page'] ) && is_string( $params['page'] ) ? esc_url_raw( $params['page'] ) : '';
		$token  = self::check_token( isset( $params['token'] ) ? (string) $params['token'] : '', $automation['id'] );
		$host   = is_array( $token ) ? $token['host'] : self::host_of( $page );

		$log = array(
			'automation_id'   => $automation['id'],
			'automation_name' => $automation['name'],
			'webhook_url'     => '' !== $page ? $page : $host,
			'method'          => 'POST',
			'remote_ip'       => $ip,
			'received_data'   => self::preview_fields( $config['fields'], $input ),
			'source'          => 'widget',
		);

		if ( is_wp_error( $token ) ) {
			return self::reject( $log, $automation['id'], $host, 403, $token->get_error_code(), $token->get_error_message() );
		}
		if ( time() - $token['issued'] < self::MIN_SECONDS ) {
			return self::reject( $log, $automation['id'], $host, 400, 'glocalsaino_aat_widget_too_fast', __( 'The form was sent too fast to be a person. Please try again.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}
		if ( ! empty( $params['hp'] ) ) {
			// Pretend it worked, so bots do not learn about the trap.
			self::reject( $log, $automation['id'], $host, 400, 'glocalsaino_aat_widget_honeypot', __( 'The hidden anti-spam field was filled in (probably a bot).', 'glocalsaino-actions-automation-based-on-trigger' ) );
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'link' === $config['kind'] ? '' : $config['message'],
				),
				200
			);
		}
		if ( self::rate_limited( $automation['id'], $ip, (int) $config['rate_limit'] ) ) {
			return self::reject( $log, $automation['id'], $host, 429, 'glocalsaino_aat_widget_rate_limit', __( 'Too many submissions from this connection. Please try again later.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}
		if ( '' !== $config['captcha'] && GSAAT_Captcha::configured( $config['captcha'] ) ) {
			$response = isset( $params['captcha'] ) && is_string( $params['captcha'] ) ? $params['captcha'] : '';
			if ( ! GSAAT_Captcha::verify( $config['captcha'], $response, $ip ) ) {
				return self::reject( $log, $automation['id'], $host, 403, 'glocalsaino_aat_widget_captcha', __( 'The verification failed. Please try again.', 'glocalsaino-actions-automation-based-on-trigger' ) );
			}
		}
		if ( 'active' !== GSAAT_Automations::availability( $automation ) ) {
			return self::reject( $log, $automation['id'], $host, 403, 'glocalsaino_aat_widget_unavailable', __( 'This form is not available right now.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		}

		list( $data, $errors ) = self::validate_fields( 'form' === $config['kind'] ? $config['fields'] : array(), $input );
		if ( $errors ) {
			$log['received_data'] = $data;
			return self::reject( $log, $automation['id'], $host, 400, 'glocalsaino_aat_widget_invalid', __( 'Please check the highlighted fields.', 'glocalsaino-actions-automation-based-on-trigger' ), $errors );
		}

		self::mark_token_used( $token['nonce'] );
		$log['received_data'] = $data;
		GSAAT_Logs::add_webhook( $log + array( 'status_code' => 200 ) );
		GSAAT_Widget_Stats::bump( $automation['id'], $host, 'submissions' );

		GSAAT_Engine::process(
			$automation,
			$data,
			'' !== $host
				/* translators: %s: website. */
				? sprintf( __( 'Widget used on %s', 'glocalsaino-actions-automation-based-on-trigger' ), $host )
				: __( 'Widget used', 'glocalsaino-actions-automation-based-on-trigger' ),
			array(
				'_widget_site' => $host,
				'_widget_page' => $page,
			),
			array(
				'user_id' => 0,
				'browser' => true,
			)
		);

		$redirect = GSAAT_Redirect::take();
		if ( '' === $redirect ) {
			$redirect = 'link' === $config['kind'] ? $config['destination'] : $config['redirect'];
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'message'  => 'link' === $config['kind'] ? '' : $config['message'],
				'redirect' => $redirect,
			),
			200
		);
	}

	private static function automation_from_request( WP_REST_Request $request ) {
		$url_params = $request->get_url_params();
		$automation = GSAAT_Automations::get_by_webhook_key( isset( $url_params['key'] ) ? (string) $url_params['key'] : '' );
		return $automation && 'widget' === $automation['trigger_type'] ? $automation : null;
	}

	private static function reject( array $log, $automation_id, $host, $status, $code, $message, array $field_errors = array() ) {
		GSAAT_Logs::add_webhook(
			$log + array(
				'status_code'   => $status,
				'error_code'    => $code,
				'error_message' => $message,
			)
		);
		GSAAT_Widget_Stats::bump( $automation_id, $host, 'rejected' );
		$data = array( 'status' => $status );
		if ( $field_errors ) {
			$data['fields'] = $field_errors;
		}
		return new WP_Error( $code, $message, $data );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Token, rate limit, fields
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Signed token: automation | embedding host | issue time | random nonce.
	 *
	 * @param int    $automation_id Automation ID.
	 * @param string $host          Embedding website.
	 * @return string
	 */
	public static function make_token( $automation_id, $host ) {
		$payload = implode( '|', array( (int) $automation_id, $host, time(), wp_generate_password( 16, false, false ) ) );
		return bin2hex( $payload ) . '.' . hash_hmac( 'sha256', $payload, self::secret() );
	}

	/**
	 * Validate a token: signature, automation, age and single use.
	 *
	 * @param string $token         Token.
	 * @param int    $automation_id Automation ID.
	 * @return array|WP_Error { host, issued, nonce }
	 */
	public static function check_token( $token, $automation_id ) {
		$expired = new WP_Error( 'glocalsaino_aat_widget_token', __( 'The form expired. Reload the page and try again.', 'glocalsaino-actions-automation-based-on-trigger' ) );
		$parts   = explode( '.', $token );
		if ( 2 !== count( $parts ) || ! preg_match( '/^[0-9a-f]+$/', $parts[0] ) || 0 !== strlen( $parts[0] ) % 2 ) {
			return $expired;
		}
		$payload = (string) hex2bin( $parts[0] );
		if ( ! hash_equals( hash_hmac( 'sha256', $payload, self::secret() ), $parts[1] ) ) {
			return $expired;
		}
		$fields = explode( '|', $payload );
		if ( 4 !== count( $fields ) || (int) $fields[0] !== (int) $automation_id ) {
			return $expired;
		}
		$issued = (int) $fields[2];
		if ( time() - $issued > self::TOKEN_LIFETIME || get_transient( self::used_key( $fields[3] ) ) ) {
			return $expired;
		}
		return array(
			'host'   => $fields[1],
			'issued' => $issued,
			'nonce'  => $fields[3],
		);
	}

	private static function mark_token_used( $nonce ) {
		set_transient( self::used_key( $nonce ), 1, self::TOKEN_LIFETIME );
	}

	private static function used_key( $nonce ) {
		return 'glocalsaino_aat_wt_' . md5( $nonce );
	}

	private static function secret() {
		return wp_salt( 'nonce' ) . 'glocalsaino_aat_widget';
	}

	/**
	 * Count a submission and say whether the per-IP hourly limit is exceeded.
	 *
	 * @param int    $automation_id Automation ID.
	 * @param string $ip            Visitor IP.
	 * @param int    $limit         Submissions per hour (0 = unlimited).
	 * @return bool
	 */
	private static function rate_limited( $automation_id, $ip, $limit ) {
		if ( $limit <= 0 ) {
			return false;
		}
		$key   = 'glocalsaino_aat_wr_' . md5( $ip . '|' . $automation_id );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return true;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * Validate and sanitize the configured fields; anything else is ignored.
	 *
	 * @param array $fields Field definitions.
	 * @param array $input  Submitted values.
	 * @return array [ data, errors ]
	 */
	public static function validate_fields( array $fields, array $input ) {
		$data   = array();
		$errors = array();
		foreach ( $fields as $field ) {
			$key = $field['key'];
			$raw = isset( $input[ $key ] ) ? $input[ $key ] : '';

			if ( in_array( $field['type'], array( 'checkbox', 'consent' ), true ) ) {
				$value = ( true === $raw || ( is_scalar( $raw ) && ! in_array( strtolower( (string) $raw ), array( '', '0', 'false', 'off' ), true ) ) ) ? 'yes' : '';
			} else {
				$value = is_scalar( $raw ) ? ( 'textarea' === $field['type'] ? sanitize_textarea_field( (string) $raw ) : sanitize_text_field( (string) $raw ) ) : '';
				$max   = 'textarea' === $field['type'] ? 5000 : 500;
				$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
			}

			$data[ $key ] = $value;

			if ( '' === $value ) {
				if ( ! empty( $field['required'] ) || 'consent' === $field['type'] ) {
					$errors[ $key ] = 'consent' === $field['type']
						? __( 'You must accept to continue.', 'glocalsaino-actions-automation-based-on-trigger' )
						: __( 'This field is required.', 'glocalsaino-actions-automation-based-on-trigger' );
				}
				continue;
			}
			if ( 'email' === $field['type'] && ! is_email( $value ) ) {
				$errors[ $key ] = __( 'Enter a valid email address.', 'glocalsaino-actions-automation-based-on-trigger' );
			} elseif ( 'number' === $field['type'] && null === GSAAT_Calculator::to_number( $value ) ) {
				$errors[ $key ] = __( 'Enter a number.', 'glocalsaino-actions-automation-based-on-trigger' );
			} elseif ( 'select' === $field['type'] && ! in_array( $value, (array) $field['options'], true ) ) {
				$errors[ $key ] = __( 'Choose one of the options.', 'glocalsaino-actions-automation-based-on-trigger' );
			}
		}
		return array( $data, $errors );
	}

	/**
	 * Configured fields of a submission, for the log of a rejected one.
	 *
	 * @param array $fields Field definitions.
	 * @param array $input  Submitted values.
	 * @return array
	 */
	private static function preview_fields( array $fields, array $input ) {
		$preview = array();
		foreach ( $fields as $field ) {
			if ( isset( $input[ $field['key'] ] ) && is_scalar( $input[ $field['key'] ] ) ) {
				$preview[ $field['key'] ] = substr( sanitize_text_field( (string) $input[ $field['key'] ] ), 0, 500 );
			}
		}
		return $preview;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Hosts
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Lowercase host of a URL or host string ('' when invalid).
	 *
	 * @param string $value URL or host.
	 * @return string
	 */
	public static function host_of( $value ) {
		$value = trim( strtolower( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( false === strpos( $value, '//' ) ) {
			$value = 'https://' . $value;
		}
		$host = (string) wp_parse_url( $value, PHP_URL_HOST );
		return preg_match( '/^[a-z0-9.\-]+$/', $host ) ? $host : '';
	}

	/**
	 * Whether a host matches the allowed list ("example.com" or "*.example.com").
	 *
	 * @param string   $host    Host.
	 * @param string[] $domains Allowed entries.
	 * @return bool
	 */
	public static function host_allowed( $host, array $domains ) {
		// This site itself is always allowed (preview in the automation editor).
		if ( $host === self::host_of( home_url() ) ) {
			return true;
		}
		foreach ( $domains as $domain ) {
			if ( 0 === strpos( $domain, '*.' ) ) {
				$base = substr( $domain, 2 );
				if ( $host === $base || substr( $host, -strlen( '.' . $base ) ) === '.' . $base ) {
					return true;
				}
			} elseif ( $host === $domain ) {
				return true;
			}
		}
		return false;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Back end
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Sanitize the widget trigger settings.
	 *
	 * @param array $post    Unslashed POST data.
	 * @param array $notices Notice codes.
	 * @return array
	 */
	public static function sanitize_config( array $post, array &$notices ) {
		$text = static function ( $key, $default = '' ) use ( $post ) {
			return isset( $post[ $key ] ) && is_string( $post[ $key ] ) ? trim( sanitize_text_field( $post[ $key ] ) ) : $default;
		};

		$kind    = $text( 'widget_kind', 'form' );
		$captcha = $text( 'widget_captcha' );
		$domains = array();
		foreach ( preg_split( '/[\s,;]+/', isset( $post['widget_domains'] ) ? (string) $post['widget_domains'] : '' ) as $entry ) {
			$wildcard = 0 === strpos( trim( $entry ), '*.' );
			$host     = self::host_of( $wildcard ? substr( trim( $entry ), 2 ) : $entry );
			if ( '' !== $host && ( false !== strpos( $host, '.' ) || 'localhost' === $host ) ) {
				$domains[] = ( $wildcard ? '*.' : '' ) . $host;
			}
		}

		$fonts  = self::fonts();
		$font   = $text( 'widget_font', 'system' );
		$config = array(
			'kind'        => array_key_exists( $kind, self::kinds() ) ? $kind : 'form',
			'title'       => $text( 'widget_title' ),
			'intro'       => isset( $post['widget_intro'] ) && is_string( $post['widget_intro'] ) ? sanitize_textarea_field( $post['widget_intro'] ) : '',
			'text'        => $text( 'widget_text', __( 'Send', 'glocalsaino-actions-automation-based-on-trigger' ) ),
			'destination' => esc_url_raw( $text( 'widget_destination' ) ),
			'fields'      => self::sanitize_fields( json_decode( isset( $post['widget_fields_json'] ) ? (string) $post['widget_fields_json'] : '[]', true ) ),
			'message'     => $text( 'widget_message' ),
			'redirect'    => esc_url_raw( $text( 'widget_redirect' ) ),
			'design'      => array(
				'primary'    => self::color( $text( 'widget_primary' ), '#2271b1' ),
				'text'       => self::color( $text( 'widget_text_color' ), '#1d2327' ),
				'background' => self::color( $text( 'widget_background' ), '#ffffff' ),
				'radius'     => max( 0, min( 40, absint( $text( 'widget_radius', '6' ) ) ) ),
				'font'       => isset( $fonts[ $font ] ) ? $font : 'system',
				'site_css'   => ! empty( $post['widget_site_css'] ),
			),
			'domains'     => array_values( array_unique( $domains ) ),
			'rate_limit'  => min( 1000, absint( $text( 'widget_rate_limit', '10' ) ) ),
			'captcha'     => array_key_exists( $captcha, GSAAT_Captcha::providers() ) ? $captcha : '',
		);

		if ( ( 'link' === $config['kind'] && '' === $config['destination'] ) || ( 'form' === $config['kind'] && ! $config['fields'] ) ) {
			$config['_incomplete'] = true;
		}
		if ( '' !== $config['captcha'] && ! GSAAT_Captcha::configured( $config['captcha'] ) ) {
			$notices[] = 'captcha_not_configured';
		}
		return $config;
	}

	private static function sanitize_fields( $fields ) {
		$clean = array();
		$keys  = array();
		$types = self::field_types();
		foreach ( is_array( $fields ) ? $fields : array() as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$key = sanitize_key( isset( $field['key'] ) ? (string) $field['key'] : '' );
			if ( '' === $key || in_array( $key, self::RESERVED, true ) || in_array( $key, $keys, true ) ) {
				continue;
			}
			$type    = isset( $field['type'], $types[ $field['type'] ] ) ? $field['type'] : 'text';
			$options = array();
			foreach ( isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array() as $option ) {
				$option = is_scalar( $option ) ? sanitize_text_field( (string) $option ) : '';
				if ( '' !== $option && ! in_array( $option, $options, true ) ) {
					$options[] = $option;
				}
			}
			$keys[]  = $key;
			$clean[] = array(
				'key'         => $key,
				'label'       => isset( $field['label'] ) ? sanitize_text_field( (string) $field['label'] ) : $key,
				'type'        => $type,
				'required'    => ! empty( $field['required'] ) || 'consent' === $type,
				'placeholder' => isset( $field['placeholder'] ) ? sanitize_text_field( (string) $field['placeholder'] ) : '',
				'options'     => 'select' === $type ? $options : array(),
				'privacy_url' => 'consent' === $type && isset( $field['privacy_url'] ) ? esc_url_raw( (string) $field['privacy_url'] ) : '',
			);
		}
		return $clean;
	}

	public static function describe( array $automation ) {
		$config = self::config( $automation['trigger_config'] );
		$kinds  = self::kinds();
		$text   = sprintf(
			/* translators: %s: widget kind. */
			__( 'Embeddable widget: %s', 'glocalsaino-actions-automation-based-on-trigger' ),
			isset( $kinds[ $config['kind'] ] ) ? $kinds[ $config['kind'] ] : $config['kind']
		);
		return $config['domains']
			/* translators: %s: comma-separated websites. */
			? $text . ' — ' . sprintf( __( 'on %s', 'glocalsaino-actions-automation-based-on-trigger' ), implode( ', ', $config['domains'] ) )
			: $text;
	}
}
