<?php
/**
 * Triggers: click on a tracked link, click on a button.
 *
 * Link:   https://site/?glocalsaino_aat_link=KEY fires the automation and redirects to the
 *         destination configured in the back end (or to a "Redirect" action's URL).
 * Button: [glocalsaino_aat_button id="ID"] renders a button configured in the back end; it posts
 *         to the current page, fires the automation and redirects back (or elsewhere).
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Trigger_Click {

	const LINK_ARG    = 'glocalsaino_aat_link';
	const BUTTON_ARG  = 'glocalsaino_aat_button';
	const CLICKED_ARG = 'glocalsaino_aat_clicked';
	const NONCE_FIELD = 'glocalsaino_aat_nonce';
	const BOTS        = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|whatsapp|telegram|discord|skype|linkedin|mediapartners|headless|python|curl|wget|scanner|monitor/i';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_request' ), 1 );
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
		return add_query_arg( self::LINK_ARG, $key, home_url( '/' ) );
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
				'label'    => __( 'Send', 'glocalsaino-actions-automation-based-on-trigger' ),
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

	public static function handle_request() {
		// phpcs:disable WordPress.Security.NonceVerification -- Public links / buttons; logged-in buttons are verified below.
		if ( isset( $_GET[ self::LINK_ARG ] ) ) {
			self::handle_link( preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_GET[ self::LINK_ARG ] ) ) ) );
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'post' === $method && isset( $_POST[ self::BUTTON_ARG ] ) ) {
			self::handle_button( preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_POST[ self::BUTTON_ARG ] ) ) ) );
		}
		// phpcs:enable
	}

	private static function handle_link( $key ) {
		$automation = '' !== $key ? GSAAT_Automations::get_by_webhook_key( $key ) : null;
		if ( ! $automation || 'link' !== $automation['trigger_type'] ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$config = self::link_config( $automation['trigger_config'] );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public tracked link.
		$params = array();
		foreach ( wp_unslash( $_GET ) as $name => $value ) {
			if ( self::LINK_ARG !== $name && is_scalar( $value ) ) {
				$params[ sanitize_key( $name ) ] = sanitize_text_field( (string) $value );
			}
		}
		// phpcs:enable

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		$is_bot = $config['ignore_bots'] && self::is_bot();

		if ( 'HEAD' !== $method && ! $is_bot && ! self::is_repeat( $automation['id'] ) ) {
			GSAAT_Engine::process(
				$automation,
				$params,
				__( 'Link clicked', 'glocalsaino-actions-automation-based-on-trigger' ),
				array(
					'_click_page'       => self::referer(),
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

		nocache_headers();
		// The destination is set by an administrator and may be another site.
		wp_redirect( esc_url_raw( $target ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	private static function handle_button( $key ) {
		$automation = '' !== $key ? GSAAT_Automations::get_by_webhook_key( $key ) : null;
		if ( ! $automation || 'button' !== $automation['trigger_type'] ) {
			return;
		}

		$config  = self::button_config( $automation['trigger_config'] );
		$user_id = 0;
		if ( is_user_logged_in() ) {
			$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
			// Without a valid nonce (e.g. a forged cross-site post) the click counts as anonymous.
			$user_id = wp_verify_nonce( $nonce, 'glocalsaino_aat_button_' . $automation['id'] ) ? get_current_user_id() : 0;
		}

		if ( ! self::is_repeat( $automation['id'] ) ) {
			GSAAT_Engine::process(
				$automation,
				array(),
				__( 'Button clicked', 'glocalsaino-actions-automation-based-on-trigger' ),
				array(
					'_click_page'   => self::referer(),
					'_button_label' => $config['label'],
				),
				array(
					'user_id' => $user_id,
					'browser' => true,
				)
			);
		}

		$target = GSAAT_Redirect::take();
		nocache_headers();
		if ( '' !== $target ) {
			wp_redirect( esc_url_raw( $target ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Validated by the Redirect action.
			exit;
		}
		if ( '' !== $config['redirect'] ) {
			wp_redirect( esc_url_raw( $config['redirect'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Set by an administrator.
			exit;
		}
		// Back to the page with the button (wp_get_referer() ignores it because it is the current URL).
		$back = wp_validate_redirect( (string) wp_get_raw_referer(), home_url( '/' ) );
		wp_safe_redirect( add_query_arg( self::CLICKED_ARG, $automation['id'], $back ) );
		exit;
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

		$html = '<form method="post" class="glocalsaino-aat-button-form">';
		$html .= '<input type="hidden" name="' . esc_attr( self::BUTTON_ARG ) . '" value="' . esc_attr( $automation['webhook_key'] ) . '">';
		if ( is_user_logged_in() ) {
			$html .= wp_nonce_field( 'glocalsaino_aat_button_' . $automation['id'], self::NONCE_FIELD, false, false );
		}
		$html .= '<button type="submit" class="' . esc_attr( trim( 'wp-element-button glocalsaino-aat-button ' . $config['class'] ) ) . '">' . esc_html( $config['label'] ) . '</button>';
		$html .= '</form>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag.
		$clicked = isset( $_GET[ self::CLICKED_ARG ] ) ? absint( wp_unslash( $_GET[ self::CLICKED_ARG ] ) ) : 0;
		if ( $clicked === $automation['id'] && '' !== $config['message'] ) {
			$html .= '<p class="glocalsaino-aat-button-message" role="status">' . esc_html( $config['message'] ) . '</p>';
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
			if ( '' !== $param && self::LINK_ARG !== $param && ! in_array( $param, $params, true ) ) {
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
			'label'    => '' !== $label ? $label : __( 'Send', 'glocalsaino-actions-automation-based-on-trigger' ),
			'class'    => isset( $post['button_class'] ) ? implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $post['button_class'] ) ) ) ) : '',
			'message'  => isset( $post['button_message'] ) ? sanitize_text_field( (string) $post['button_message'] ) : '',
			'redirect' => isset( $post['button_redirect'] ) ? esc_url_raw( trim( (string) $post['button_redirect'] ) ) : '',
		);
	}

	public static function describe_link( array $automation ) {
		$config = self::link_config( $automation['trigger_config'] );
		/* translators: %s: destination URL. */
		return sprintf( __( 'Link clicked → %s', 'glocalsaino-actions-automation-based-on-trigger' ), $config['destination'] );
	}

	public static function describe_button( array $automation ) {
		$config = self::button_config( $automation['trigger_config'] );
		/* translators: %s: button label. */
		return sprintf( __( 'Button "%s" clicked', 'glocalsaino-actions-automation-based-on-trigger' ), $config['label'] );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------
	 */

	private static function is_bot() {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
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

	private static function referer() {
		$referer = wp_get_raw_referer();
		return $referer ? esc_url_raw( $referer ) : '';
	}
}
