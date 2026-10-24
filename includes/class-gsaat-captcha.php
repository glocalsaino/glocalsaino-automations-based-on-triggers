<?php
/**
 * Optional captcha for embeddable widgets: hCaptcha.
 * Nothing is loaded or sent to these services unless a widget uses them.
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Captcha {

	/**
	 * Providers: key => { label, script, verify }.
	 *
	 * @return array
	 */
	public static function providers() {
		return array(
			'hcaptcha' => array(
				'label'  => 'hCaptcha',
				'script' => 'https://js.hcaptcha.com/1/api.js?render=explicit',
				'verify' => 'https://api.hcaptcha.com/siteverify',
			),
		);
	}

	/**
	 * Whether a provider has both keys in the settings.
	 *
	 * @param string $provider Provider key.
	 * @return bool
	 */
	public static function configured( $provider ) {
		$keys = self::keys( $provider );
		return '' !== $keys['site'] && '' !== $keys['secret'];
	}

	/**
	 * Keys of a provider.
	 *
	 * @param string $provider Provider key.
	 * @return array { site, secret }
	 */
	public static function keys( $provider ) {
		$settings = GSAAT_Plugin::settings();
		return array(
			'site'   => isset( $settings[ $provider . '_site_key' ] ) ? (string) $settings[ $provider . '_site_key' ] : '',
			'secret' => isset( $settings[ $provider . '_secret_key' ] ) ? (string) $settings[ $provider . '_secret_key' ] : '',
		);
	}

	/**
	 * Check a captcha response with the provider.
	 *
	 * @param string $provider Provider key.
	 * @param string $response Response token from the widget.
	 * @param string $ip       Visitor IP.
	 * @return bool
	 */
	public static function verify( $provider, $response, $ip ) {
		$providers = self::providers();
		if ( ! isset( $providers[ $provider ] ) || '' === $response || ! self::configured( $provider ) ) {
			return false;
		}
		$keys   = self::keys( $provider );
		$result = wp_remote_post(
			$providers[ $provider ]['verify'],
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $keys['secret'],
					'response' => $response,
					'remoteip' => $ip,
				),
			)
		);
		if ( is_wp_error( $result ) ) {
			return false;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $result ), true );
		return is_array( $body ) && ! empty( $body['success'] );
	}
}
