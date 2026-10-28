<?php
/**
 * Triggers: click on a tracked link, click on a button.
 *
 * Both are public REST routes (they run for anonymous visitors by design, like a webhook):
 * Link:   GET  /wp-json/glocalsaino-aat/v1/link/KEY fires the automation and redirects to the
 *         destination configured in the back end (or to a "Redirect" action's URL).
 * Button: POST /wp-json/glocalsaino-aat/v1/button/KEY from the [glocalsaino_aat_button] form fires
 *         the automation and redirects back (or elsewhere). Logged-in visitors are identified
 *         through WordPress's REST cookie authentication (the form carries the wp_rest nonce).
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Trigger_Click {

	const BOTS        = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|whatsapp|telegram|discord|skype|linkedin|mediapartners|headless|python|curl|wget|scanner|monitor/i';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_shortcode( 'glocalsaino_aat_link', array( __CLASS__, 'link_shortcode' ) );
		add_shortcode( 'glocalsaino_aat_button', array( __CLASS__, 'button_shortcode' ) );
	}

	/**
	 * Public URL of a tracked link.
	 *
	 * @param string $key Automation webhook key.
	 * @return string
	 */
	public static function link_url( $key ) {
		return rest_url( GSAAT_Automations::REST_NAMESPACE . '/link/' . $key );
	}

	public static function link_config( array $config ) {
		return wp_parse_args(
			$config,
			array(
				'destination' => '',
				'params'      => array(),
				'pass_params' => false,
				'ignore_bots' => true,
			)
		);
	}

	public static function button_config( array $config ) {
		return wp_parse_args(
			$config,
			array(
				'label'    => __( 'Send', 'glocalsaino-automations-based-on-triggers' ),
				'class'    => '',
				'message'  => '',
				'redirect' => '',
			)
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Front end
	 * ---------------------------------------------------------------------
	 */

	public static function register_routes() {
		register_rest_route(
			GSAAT_Automations::REST_NAMESPACE,
			'/link/(?P<key>[A-Za-z0-9]{20,64})',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_link' ),
				// Public by design: a tracked link can be clicked by anyone, also from emails and other websites.
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			GSAAT_Automations::REST_NAMESPACE,
			'/button/(?P<key>[A-Za-z0-9]{20,64})',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_button' ),
				// Public by design: anonymous visitors can click the button. Logged-in visitors are
				// identified only through the wp_rest nonce checked by WordPress's REST cookie authentication.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Tracked link clicked.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_link( WP_REST_Request $request ) {
		$automation = self::automation_from_request( $request, 'link' );
		if ( ! $automation ) {
			return GSAAT_Redirect::rest_redirect( home_url( '/' ) );
		}

		$config = self::link_config( $automation['trigger_config'] );
		$params = array();
		foreach ( (array) $request->get_query_params() as $name => $value ) {
			if ( 'rest_route' !== $name && '_wpnonce' !== $name && is_scalar( $value ) ) {
				$params[ sanitize_key( $name ) ] = sanitize_text_field( (string) $value );
			}
		}

		$is_bot = $config['ignore_bots'] && self::is_bot( (string) $request->get_header( 'user_agent' ) );
		if ( 'HEAD' !== $request->get_method() && ! $is_bot && ! self::is_repeat( $automation['id'] )
			&& ( get_current_user_id() || GSAAT_Automations::public_run_allowed( $automation ) ) ) {
			GSAAT_Engine::process(
				$automation,
				$params,
				__( 'Link clicked', 'glocalsaino-automations-based-on-triggers' ),
				array(
					'_click_page'       => self::referer( $request ),
					'_link_destination' => $config['destination'],
				),
				array(
					'user_id' => get_current_user_id(),
					'browser' => true,
				)
			);
		}

		$target = GSAAT_Redirect::take();
		if ( '' === $target ) {
			$target = '' !== $config['destination'] ? $config['destination'] : home_url( '/' );
			if ( $config['pass_params'] && $params ) {
				$target = add_query_arg( array_map( 'rawurlencode', $params ), $target );
			}
		}
		return GSAAT_Redirect::rest_redirect( $target );
	}

	/**
	 * Button clicked.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_button( WP_REST_Request $request ) {
		$automation = self::automation_from_request( $request, 'button' );
		$back       = wp_validate_redirect( (string) $request->get_header( 'referer' ), home_url( '/' ) );
		if ( ! $automation ) {
			return GSAAT_Redirect::rest_redirect( $back );
		}
		$config = self::button_config( $automation['trigger_config'] );

		if ( ! self::is_repeat( $automation['id'] ) && ( get_current_user_id() || GSAAT_Automations::public_run_allowed( $automation ) ) ) {
			GSAAT_Engine::process(
				$automation,
				array(),
				__( 'Button clicked', 'glocalsaino-automations-based-on-triggers' ),
				array(
					'_click_page'   => self::referer( $request ),
					'_button_label' => $config['label'],
				),
				array(
					// 0 unless the wp_rest nonce of the form was valid (REST cookie authentication).
					'user_id' => get_current_user_id(),
					'browser' => true,
				)
			);
		}

		$target = GSAAT_Redirect::take();
		if ( '' === $target ) {
			$target = '' !== $config['redirect']
				? $config['redirect']
				: strtok( $back, '#' ) . '#glocalsaino-aat-clicked-' . $automation['id'];
		}
		return GSAAT_Redirect::rest_redirect( $target );
	}

	private static function automation_from_request( WP_REST_Request $request, $type ) {
		$url_params = $request->get_url_params();
		$automation = GSAAT_Automations::get_by_webhook_key( isset( $url_params['key'] ) ? (string) $url_params['key'] : '' );
		return $automation && $type === $automation['trigger_type'] ? $automation : null;
	}
	/**
	 * [glocalsaino_aat_link id="12"]Text[/glocalsaino_aat_link]
	 *
	 * @param array  $atts    Attributes.
	 * @param string $content Link text.
	 * @return string
	 */
	public static function link_shortcode( $atts, $content = '' ) {
		$atts       = shortcode_atts(
			array(
				'id'    => 0,
				'class' => '',
			),
			$atts,
			'glocalsaino_aat_link'
		);
		$automation = GSAAT_Automations::get( absint( $atts['id'] ) );
		if ( ! $automation || 'link' !== $automation['trigger_type'] ) {
			return '';
		}
		$text = '' !== trim( (string) $content ) ? do_shortcode( $content ) : esc_html( self::link_url( $automation['webhook_key'] ) );
		return sprintf(
			'<a href="%1$s" class="%2$s">%3$s</a>',
			esc_url( self::link_url( $automation['webhook_key'] ) ),
			esc_attr( trim( 'glocalsaino-aat-link ' . $atts['class'] ) ),
			wp_kses_post( $text )
		);
	}

	/**
	 * [glocalsaino_aat_button id="12"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function button_shortcode( $atts ) {
		$atts       = shortcode_atts( array( 'id' => 0 ), $atts, 'glocalsaino_aat_button' );
		$automation = GSAAT_Automations::get( absint( $atts['id'] ) );
		if ( ! $automation || 'button' !== $automation['trigger_type'] ) {
			return '';
		}
		$config = self::button_config( $automation['trigger_config'] );

		wp_enqueue_style( 'glocalsaino-aat-front', GSAAT_URL . 'assets/css/front.css', array(), GSAAT_VERSION );

		$html = '<form method="post" class="glocalsaino-aat-button-form" action="' . esc_url( rest_url( GSAAT_Automations::REST_NAMESPACE . '/button/' . $automation['webhook_key'] ) ) . '">';
		if ( is_user_logged_in() ) {
			// WordPress's REST nonce: identifies the logged-in visitor (and blocks forged cross-site posts on their behalf).
			$html .= wp_nonce_field( 'wp_rest', '_wpnonce', false, false );
		}
		$html .= '<button type="submit" class="' . esc_attr( trim( 'wp-element-button glocalsaino-aat-button ' . $config['class'] ) ) . '">' . esc_html( $config['label'] ) . '</button>';
		$html .= '</form>';

		if ( '' !== $config['message'] ) {
			// Hidden until the page is reloaded with #glocalsaino-aat-clicked-ID (CSS :target).
			$html .= '<p class="glocalsaino-aat-target-message glocalsaino-aat-button-message" id="glocalsaino-aat-clicked-' . esc_attr( (string) $automation['id'] ) . '" role="status">' . esc_html( $config['message'] ) . '</p>';
		}
		return $html;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Back end
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Sanitize the link trigger settings.
	 *
	 * @param array $post    Unslashed POST data.
	 * @param array $notices Notice codes.
	 * @return array
	 */
	public static function sanitize_link( array $post, array &$notices ) {
		$params = array();
		foreach ( preg_split( '/[\s,;]+/', isset( $post['link_params'] ) ? (string) $post['link_params'] : '' ) as $param ) {
			$param = sanitize_key( $param );
			if ( '' !== $param && ! in_array( $param, array( 'rest_route', '_wpnonce' ), true ) && ! in_array( $param, $params, true ) ) {
				$params[] = $param;
			}
		}
		$config = array(
			'destination' => isset( $post['link_destination'] ) ? esc_url_raw( trim( (string) $post['link_destination'] ) ) : '',
			'params'      => $params,
			'pass_params' => ! empty( $post['link_pass_params'] ),
			'ignore_bots' => ! empty( $post['link_ignore_bots'] ),
		);
		if ( '' === $config['destination'] ) {
			$config['_incomplete'] = true;
		}
		return $config;
	}

	public static function sanitize_button( array $post, array &$notices ) {
		$label = isset( $post['button_label'] ) ? sanitize_text_field( (string) $post['button_label'] ) : '';
		return array(
			'label'    => '' !== $label ? $label : __( 'Send', 'glocalsaino-automations-based-on-triggers' ),
			'class'    => isset( $post['button_class'] ) ? implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $post['button_class'] ) ) ) ) : '',
			'message'  => isset( $post['button_message'] ) ? sanitize_text_field( (string) $post['button_message'] ) : '',
			'redirect' => isset( $post['button_redirect'] ) ? esc_url_raw( trim( (string) $post['button_redirect'] ) ) : '',
		);
	}

	public static function describe_link( array $automation ) {
		$config = self::link_config( $automation['trigger_config'] );
		if ( '' === $config['destination'] ) {
			return __( 'Link clicked', 'glocalsaino-automations-based-on-triggers' );
		}
		/* translators: %s: destination URL. */
		return sprintf( __( 'Link clicked → %s', 'glocalsaino-automations-based-on-triggers' ), $config['destination'] );
	}

	public static function describe_button( array $automation ) {
		$config = self::button_config( $automation['trigger_config'] );
		/* translators: %s: button label. */
		return sprintf( __( 'Button "%s" clicked', 'glocalsaino-automations-based-on-triggers' ), $config['label'] );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------
	 */

	private static function is_bot( $agent ) {
		$agent = sanitize_text_field( $agent );
		return '' === $agent || 1 === preg_match( self::BOTS, $agent );
	}

	/**
	 * Ignore repeated clicks of the same visitor on the same trigger within 10 seconds (double clicks, reloads).
	 *
	 * @param int $automation_id Automation ID.
	 * @return bool
	 */
	private static function is_repeat( $automation_id ) {
		$key = 'glocalsaino_aat_click_' . md5( GSAAT_Webhook_Security::client_ip() . '|' . $automation_id );
		if ( get_transient( $key ) ) {
			return true;
		}
		set_transient( $key, 1, 10 );
		return false;
	}

	private static function referer( WP_REST_Request $request ) {
		$referer = (string) $request->get_header( 'referer' );
		return '' !== $referer ? esc_url_raw( $referer ) : '';
	}
}
