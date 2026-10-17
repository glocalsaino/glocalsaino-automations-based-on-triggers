<?php
/**
 * Incoming webhook protection: header token, IP allowlist and HMAC signature.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Webhook_Security {

	const DEFAULT_TOKEN_HEADER     = 'X-Webhook-Token';
	const DEFAULT_SIGNATURE_HEADER = 'X-Signature';
	const DEFAULT_TOLERANCE        = 300;

	/**
	 * Signature presets.
	 *
	 * @return array
	 */
	public static function presets() {
		return array(
			'generic'     => __( 'Custom (generic HMAC)', 'glocalsaino-actions-automation-based-on-trigger' ),
			'stripe'      => 'Stripe',
			'shopify'     => 'Shopify',
			'woocommerce' => 'WooCommerce',
			'github'      => 'GitHub',
		);
	}

	public static function algorithms() {
		return array( 'sha256', 'sha512', 'sha1' );
	}

	/**
	 * Security settings of a webhook automation, merged with defaults.
	 *
	 * @param array $trigger_config Trigger configuration.
	 * @return array
	 */
	public static function config( array $trigger_config ) {
		$saved = isset( $trigger_config['security'] ) && is_array( $trigger_config['security'] ) ? $trigger_config['security'] : array();
		$base  = array(
			'token'     => array(
				'enabled' => false,
				'header'  => self::DEFAULT_TOKEN_HEADER,
				'value'   => '',
			),
			'ip'        => array(
				'enabled' => false,
				'list'    => array(),
			),
			'signature' => array(
				'enabled'          => false,
				'preset'           => 'generic',
				'secret'           => '',
				'header'           => self::DEFAULT_SIGNATURE_HEADER,
				'algo'             => 'sha256',
				'encoding'         => 'hex',
				'timestamp_header' => '',
				'tolerance'        => self::DEFAULT_TOLERANCE,
			),
		);
		foreach ( $base as $layer => $defaults ) {
			$base[ $layer ] = wp_parse_args( isset( $saved[ $layer ] ) && is_array( $saved[ $layer ] ) ? $saved[ $layer ] : array(), $defaults );
		}
		return $base;
	}

	/**
	 * Names of the enabled protections, for display.
	 *
	 * @param array $security Security config.
	 * @return string[]
	 */
	public static function enabled_labels( array $security ) {
		$labels = array();
		if ( $security['token']['enabled'] ) {
			$labels[] = __( 'token', 'glocalsaino-actions-automation-based-on-trigger' );
		}
		if ( $security['ip']['enabled'] ) {
			$labels[] = __( 'IP list', 'glocalsaino-actions-automation-based-on-trigger' );
		}
		if ( $security['signature']['enabled'] ) {
			$presets = self::presets();
			/* translators: %s: signature preset name. */
			$labels[] = sprintf( __( '%s signature', 'glocalsaino-actions-automation-based-on-trigger' ), 'generic' === $security['signature']['preset'] ? 'HMAC' : $presets[ $security['signature']['preset'] ] );
		}
		return $labels;
	}

	/**
	 * Run all enabled checks.
	 *
	 * @param array           $security Security config.
	 * @param WP_REST_Request $request  Request.
	 * @param string          $ip       Caller IP.
	 * @return true|array True, or [ HTTP status, error code, message ].
	 */
	public static function check( array $security, WP_REST_Request $request, $ip ) {
		if ( $security['ip']['enabled'] && ! self::ip_allowed( $ip, (array) $security['ip']['list'] ) ) {
			return array(
				403,
				'glocalsaino_aat_ip_not_allowed',
				/* translators: %s: IP address. */
				sprintf( __( 'Calls from the IP address %s are not allowed.', 'glocalsaino-actions-automation-based-on-trigger' ), '' !== $ip ? $ip : '?' ),
			);
		}

		if ( $security['token']['enabled'] ) {
			$header = $security['token']['header'];
			$sent   = trim( (string) $request->get_header( $header ) );
			if ( 0 === strcasecmp( $header, 'Authorization' ) ) {
				$sent = preg_replace( '/^Bearer\s+/i', '', $sent );
			}
			if ( '' === $sent ) {
				/* translators: %s: header name. */
				return array( 401, 'glocalsaino_aat_missing_token', sprintf( __( 'The authentication token was not sent (expected in the %s header).', 'glocalsaino-actions-automation-based-on-trigger' ), $header ) );
			}
			if ( '' === $security['token']['value'] || ! hash_equals( (string) $security['token']['value'], $sent ) ) {
				return array( 401, 'glocalsaino_aat_invalid_token', __( 'The authentication token is not valid.', 'glocalsaino-actions-automation-based-on-trigger' ) );
			}
		}

		if ( $security['signature']['enabled'] ) {
			return self::check_signature( $security['signature'], $request );
		}

		return true;
	}

	/**
	 * WooCommerce sends an unsigned "webhook_id=123" ping when a webhook is saved.
	 *
	 * @param array           $security Security config.
	 * @param WP_REST_Request $request  Request.
	 * @return bool
	 */
	public static function is_woocommerce_ping( array $security, WP_REST_Request $request ) {
		return $security['signature']['enabled']
			&& 'woocommerce' === $security['signature']['preset']
			&& '' === (string) $request->get_header( 'X-WC-Webhook-Signature' )
			&& 1 === preg_match( '/^webhook_id=\d+$/', trim( (string) $request->get_body() ) );
	}

	/**
	 * Verify the HMAC signature of a request.
	 *
	 * @param array           $sig     Signature config.
	 * @param WP_REST_Request $request Request.
	 * @return true|array
	 */
	private static function check_signature( array $sig, WP_REST_Request $request ) {
		$secret  = (string) $sig['secret'];
		$body    = (string) $request->get_body();
		$invalid = array( 401, 'glocalsaino_aat_invalid_signature', __( 'The signature is not valid.', 'glocalsaino-actions-automation-based-on-trigger' ) );

		if ( '' === $secret ) {
			return $invalid;
		}

		switch ( $sig['preset'] ) {
			case 'stripe':
				$header = (string) $request->get_header( 'Stripe-Signature' );
				if ( '' === $header ) {
					return self::missing_signature( 'Stripe-Signature' );
				}
				$timestamp  = '';
				$signatures = array();
				foreach ( explode( ',', $header ) as $part ) {
					$pair = explode( '=', trim( $part ), 2 );
					if ( 2 !== count( $pair ) ) {
						continue;
					}
					if ( 't' === $pair[0] ) {
						$timestamp = $pair[1];
					} elseif ( 'v1' === $pair[0] ) {
						$signatures[] = $pair[1];
					}
				}
				if ( 1 !== preg_match( '/^\d+$/', $timestamp ) || ! $signatures ) {
					return $invalid;
				}
				$expired = self::expired( (int) $timestamp, (int) $sig['tolerance'] );
				if ( $expired ) {
					return $expired;
				}
				$expected = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
				foreach ( $signatures as $signature ) {
					if ( hash_equals( $expected, $signature ) ) {
						return true;
					}
				}
				return $invalid;

			case 'shopify':
			case 'woocommerce':
				$name = 'shopify' === $sig['preset'] ? 'X-Shopify-Hmac-Sha256' : 'X-WC-Webhook-Signature';
				$sent = trim( (string) $request->get_header( $name ) );
				if ( '' === $sent ) {
					return self::missing_signature( $name );
				}
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Signature encoding defined by Shopify / WooCommerce.
				return hash_equals( base64_encode( hash_hmac( 'sha256', $body, $secret, true ) ), $sent ) ? true : $invalid;

			case 'github':
				$sent = trim( (string) $request->get_header( 'X-Hub-Signature-256' ) );
				if ( '' === $sent ) {
					return self::missing_signature( 'X-Hub-Signature-256' );
				}
				return hash_equals( 'sha256=' . hash_hmac( 'sha256', $body, $secret ), strtolower( $sent ) ) ? true : $invalid;
		}

		// Generic HMAC.
		$algo = in_array( $sig['algo'], self::algorithms(), true ) ? $sig['algo'] : 'sha256';
		$sent = trim( (string) $request->get_header( $sig['header'] ) );
		if ( '' === $sent ) {
			return self::missing_signature( $sig['header'] );
		}
		if ( 0 === stripos( $sent, $algo . '=' ) ) {
			$sent = substr( $sent, strlen( $algo ) + 1 );
		}

		$signed = '' !== $body ? $body : self::raw_query_string();
		if ( '' !== $sig['timestamp_header'] ) {
			$timestamp = trim( (string) $request->get_header( $sig['timestamp_header'] ) );
			if ( 1 !== preg_match( '/^\d+$/', $timestamp ) ) {
				/* translators: %s: header name. */
				return array( 401, 'glocalsaino_aat_missing_timestamp', sprintf( __( 'The timestamp was not sent (expected in the %s header).', 'glocalsaino-actions-automation-based-on-trigger' ), $sig['timestamp_header'] ) );
			}
			$expired = self::expired( (int) $timestamp, (int) $sig['tolerance'] );
			if ( $expired ) {
				return $expired;
			}
			$signed = $timestamp . '.' . $signed;
		}

		if ( 'base64' === $sig['encoding'] ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Signature encoding chosen by the administrator.
			$expected = base64_encode( hash_hmac( $algo, $signed, $secret, true ) );
		} else {
			$expected = hash_hmac( $algo, $signed, $secret );
			$sent     = strtolower( $sent );
		}
		return hash_equals( $expected, $sent ) ? true : $invalid;
	}

	private static function missing_signature( $header ) {
		/* translators: %s: header name. */
		return array( 401, 'glocalsaino_aat_missing_signature', sprintf( __( 'The signature was not sent (expected in the %s header).', 'glocalsaino-actions-automation-based-on-trigger' ), $header ) );
	}

	/**
	 * Replay protection: reject timestamps outside the tolerance window.
	 *
	 * @param int $timestamp Unix timestamp sent by the caller.
	 * @param int $tolerance Allowed age in seconds.
	 * @return array|false Error, or false when the timestamp is fine.
	 */
	private static function expired( $timestamp, $tolerance ) {
		$tolerance = $tolerance > 0 ? $tolerance : self::DEFAULT_TOLERANCE;
		if ( abs( time() - $timestamp ) <= $tolerance ) {
			return false;
		}
		/* translators: %d: number of seconds. */
		return array( 401, 'glocalsaino_aat_signature_expired', sprintf( __( 'The call is too old or its clock is wrong (more than %d seconds of difference). It may be a replayed call.', 'glocalsaino-actions-automation-based-on-trigger' ), $tolerance ) );
	}

	private static function raw_query_string() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Must stay byte-identical to what the sender signed; only compared with hash_equals().
		return isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '';
	}

	/*
	 * ---------------------------------------------------------------------
	 * IP addresses
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Sources the caller IP can be read from (Settings screen).
	 *
	 * @return array
	 */
	public static function ip_sources() {
		return array(
			'remote_addr'      => __( 'Direct connection (REMOTE_ADDR) — recommended', 'glocalsaino-actions-automation-based-on-trigger' ),
			'x_forwarded_for'  => __( 'X-Forwarded-For header (behind a proxy or load balancer)', 'glocalsaino-actions-automation-based-on-trigger' ),
			'cf_connecting_ip' => __( 'CF-Connecting-IP header (behind Cloudflare)', 'glocalsaino-actions-automation-based-on-trigger' ),
			'x_real_ip'        => __( 'X-Real-IP header (behind Nginx proxy)', 'glocalsaino-actions-automation-based-on-trigger' ),
		);
	}

	/**
	 * IP address of the caller, according to the configured source.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$settings = GSAAT_Plugin::settings();
		$headers  = array(
			'x_forwarded_for'  => 'HTTP_X_FORWARDED_FOR',
			'cf_connecting_ip' => 'HTTP_CF_CONNECTING_IP',
			'x_real_ip'        => 'HTTP_X_REAL_IP',
		);
		$source   = $settings['ip_source'];

		if ( isset( $headers[ $source ] ) && ! empty( $_SERVER[ $headers[ $source ] ] ) ) {
			$parts     = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $headers[ $source ] ] ) ) );
			$candidate = trim( $parts[0] );
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
	}

	/**
	 * Whether an entry is a valid IP or CIDR range.
	 *
	 * @param string $entry IP, or IP/bits.
	 * @return bool
	 */
	public static function valid_ip_entry( $entry ) {
		$parts = explode( '/', $entry, 2 );
		if ( ! filter_var( $parts[0], FILTER_VALIDATE_IP ) ) {
			return false;
		}
		if ( 1 === count( $parts ) ) {
			return true;
		}
		$max = false !== strpos( $parts[0], ':' ) ? 128 : 32;
		return 1 === preg_match( '/^\d+$/', $parts[1] ) && (int) $parts[1] <= $max;
	}

	/**
	 * Whether an IP matches any entry of the list (single IPs or CIDR ranges, IPv4 or IPv6).
	 *
	 * @param string   $ip   Caller IP.
	 * @param string[] $list Allowed entries.
	 * @return bool
	 */
	public static function ip_allowed( $ip, array $list ) {
		$ip_bin = '' !== $ip ? inet_pton( $ip ) : false;
		if ( false === $ip_bin ) {
			return false;
		}

		foreach ( $list as $entry ) {
			$parts      = explode( '/', $entry, 2 );
			$subnet_bin = inet_pton( $parts[0] );
			if ( false === $subnet_bin || strlen( $subnet_bin ) !== strlen( $ip_bin ) ) {
				continue;
			}
			$bits = isset( $parts[1] ) ? (int) $parts[1] : strlen( $ip_bin ) * 8;

			$bytes = intdiv( $bits, 8 );
			if ( substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
				continue;
			}
			$remaining = $bits % 8;
			if ( 0 === $remaining ) {
				return true;
			}
			$mask = ( 0xFF << ( 8 - $remaining ) ) & 0xFF;
			if ( ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $subnet_bin[ $bytes ] ) & $mask ) ) {
				return true;
			}
		}
		return false;
	}
}
