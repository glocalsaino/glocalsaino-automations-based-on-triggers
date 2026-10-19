<?php
/**
 * Text and number operations of the "Format data" action.
 *
 * @package GlocalSainoActionsAutomation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Formatter {

	/**
	 * Operations: key => { label, params: [ label, default ] list (up to 3) }.
	 *
	 * @return array
	 */
	public static function operations() {
		$operations = array(
			'first_word'       => array( __( 'First word', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'last_word'        => array( __( 'Last word', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'nth_word'         => array( __( 'Word number…', 'glocalsaino-actions-automation-based-on-trigger' ), array( array( __( 'Position (1 = first)', 'glocalsaino-actions-automation-based-on-trigger' ), '2' ) ) ),
			'first_letter'     => array( __( 'First letter', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'initials'         => array( __( 'Initials (first letter of each word)', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'uppercase'        => array( __( 'UPPERCASE', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'lowercase'        => array( __( 'lowercase', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'capitalize_words' => array( __( 'Capitalize Each Word', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'capitalize_first' => array( __( 'Capitalize first letter', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'trim'             => array( __( 'Remove extra spaces', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'truncate'         => array(
				__( 'Shorten', 'glocalsaino-actions-automation-based-on-trigger' ),
				array(
					array( __( 'Maximum characters', 'glocalsaino-actions-automation-based-on-trigger' ), '50' ),
					array( __( 'Add at the end if shortened', 'glocalsaino-actions-automation-based-on-trigger' ), '…' ),
				),
			),
			'substring'        => array(
				__( 'Extract part of the text', 'glocalsaino-actions-automation-based-on-trigger' ),
				array(
					array( __( 'From character (1 = first)', 'glocalsaino-actions-automation-based-on-trigger' ), '1' ),
					array( __( 'Number of characters (empty = to the end)', 'glocalsaino-actions-automation-based-on-trigger' ), '' ),
				),
			),
			'replace'          => array(
				__( 'Replace text', 'glocalsaino-actions-automation-based-on-trigger' ),
				array(
					array( __( 'Search for', 'glocalsaino-actions-automation-based-on-trigger' ), '' ),
					array( __( 'Replace with', 'glocalsaino-actions-automation-based-on-trigger' ), '' ),
				),
			),
			'digits_only'      => array( __( 'Keep only digits', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'round'            => array( __( 'Round a number', 'glocalsaino-actions-automation-based-on-trigger' ), array( array( __( 'Decimals', 'glocalsaino-actions-automation-based-on-trigger' ), '0' ) ) ),
			'round_up'         => array( __( 'Round up (ceiling)', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'round_down'       => array( __( 'Round down (floor)', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
			'number_format'    => array(
				__( 'Format a number (e.g. 1.234,56)', 'glocalsaino-actions-automation-based-on-trigger' ),
				array(
					array( __( 'Decimals', 'glocalsaino-actions-automation-based-on-trigger' ), '2' ),
					array( __( 'Decimal separator', 'glocalsaino-actions-automation-based-on-trigger' ), ',' ),
					array( __( 'Thousands separator', 'glocalsaino-actions-automation-based-on-trigger' ), '.' ),
				),
			),
			'date_format'      => array( __( 'Format a date', 'glocalsaino-actions-automation-based-on-trigger' ), array( array( __( 'Format (PHP date format, e.g. d/m/Y)', 'glocalsaino-actions-automation-based-on-trigger' ), 'd/m/Y' ) ) ),
			'default'          => array( __( 'Default value if empty', 'glocalsaino-actions-automation-based-on-trigger' ), array( array( __( 'Default value', 'glocalsaino-actions-automation-based-on-trigger' ), '' ) ) ),
			'slug'             => array( __( 'Slug (lowercase-with-dashes)', 'glocalsaino-actions-automation-based-on-trigger' ), array() ),
		);

		/**
		 * Filters the "Format data" operations. Apply new ones with glocalsaino_aat_format_value.
		 *
		 * @param array $operations key => [ label, params ].
		 */
		return apply_filters( 'glocalsaino_aat_format_operations', $operations );
	}

	/**
	 * Apply an operation.
	 *
	 * @param string $operation Operation key.
	 * @param string $value     Input value.
	 * @param array  $params    Up to 3 parameters.
	 * @return string|WP_Error
	 */
	public static function apply( $operation, $value, array $params ) {
		$value = (string) $value;
		$p     = array_pad( array_map( 'strval', $params ), 3, '' );
		$words = preg_split( '/\s+/u', trim( $value ), -1, PREG_SPLIT_NO_EMPTY );

		switch ( $operation ) {
			case 'first_word':
				return $words ? $words[0] : '';
			case 'last_word':
				return $words ? end( $words ) : '';
			case 'nth_word':
				$position = max( 1, (int) $p[0] );
				return isset( $words[ $position - 1 ] ) ? $words[ $position - 1 ] : '';
			case 'first_letter':
				return self::substr( trim( $value ), 0, 1 );
			case 'initials':
				return implode( '', array_map( static function ( $word ) { return self::substr( $word, 0, 1 ); }, $words ) );
			case 'uppercase':
				return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $value ) : strtoupper( $value );
			case 'lowercase':
				return self::lower( $value );
			case 'capitalize_words':
				return function_exists( 'mb_convert_case' ) ? mb_convert_case( self::lower( $value ), MB_CASE_TITLE ) : ucwords( strtolower( $value ) );
			case 'capitalize_first':
				$text = trim( $value );
				return self::upper( self::substr( $text, 0, 1 ) ) . self::substr( $text, 1 );
			case 'trim':
				return implode( ' ', $words );
			case 'truncate':
				$max = max( 1, (int) $p[0] );
				return self::length( $value ) > $max ? self::substr( $value, 0, $max ) . $p[1] : $value;
			case 'substring':
				$start = max( 1, (int) $p[0] ) - 1;
				return '' === trim( $p[1] ) ? self::substr( $value, $start ) : self::substr( $value, $start, max( 0, (int) $p[1] ) );
			case 'replace':
				return '' === $p[0] ? $value : str_replace( $p[0], $p[1], $value );
			case 'digits_only':
				return preg_replace( '/\D+/', '', $value );
			case 'round':
			case 'round_up':
			case 'round_down':
			case 'number_format':
				$number = GSAAT_Calculator::to_number( $value );
				if ( null === $number ) {
					/* translators: %s: value. */
					return new WP_Error( 'glocalsaino_aat_nan', sprintf( __( '"%s" is not a number.', 'glocalsaino-actions-automation-based-on-trigger' ), $value ) );
				}
				if ( 'round_up' === $operation ) {
					return GSAAT_Calculator::number_to_string( ceil( $number ) );
				}
				if ( 'round_down' === $operation ) {
					return GSAAT_Calculator::number_to_string( floor( $number ) );
				}
				$decimals = max( 0, min( 10, (int) $p[0] ) );
				return 'round' === $operation
					? GSAAT_Calculator::number_to_string( $number, $decimals )
					: number_format( $number, $decimals, $p[1], $p[2] );
			case 'date_format':
				$timestamp = self::to_timestamp( $value );
				if ( null === $timestamp ) {
					/* translators: %s: value. */
					return new WP_Error( 'glocalsaino_aat_nodate', sprintf( __( '"%s" is not a recognizable date.', 'glocalsaino-actions-automation-based-on-trigger' ), $value ) );
				}
				return wp_date( '' !== $p[0] ? $p[0] : get_option( 'date_format' ), $timestamp );
			case 'default':
				return '' === trim( $value ) ? $p[0] : $value;
			case 'slug':
				return sanitize_title( $value );
		}

		/**
		 * Applies a custom "Format data" operation added with glocalsaino_aat_format_operations.
		 *
		 * @param string|null $result    Null when the operation is not handled.
		 * @param string      $operation Operation key.
		 * @param string      $value     Input value.
		 * @param array       $params    Parameters.
		 */
		$result = apply_filters( 'glocalsaino_aat_format_value', null, $operation, $value, $p );
		return null === $result
			? new WP_Error( 'glocalsaino_aat_unknown_format', __( 'Unknown format operation.', 'glocalsaino-actions-automation-based-on-trigger' ) )
			: (string) $result;
	}

	/**
	 * Parse dates like 2026-10-19, 19/10/2026, 19-10-2026 14:30 or anything strtotime() understands,
	 * in the site timezone.
	 *
	 * @param string $value Date text.
	 * @return int|null
	 */
	private static function to_timestamp( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		if ( preg_match( '/^\d{9,11}$/', $value ) ) {
			return (int) $value;
		}
		// Day-first numeric dates (European order), which strtotime() would read as month-first with "/".
		if ( preg_match( '#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})(.*)$#', $value, $m ) ) {
			$value = sprintf( '%04d-%02d-%02d%s', $m[3], $m[2], $m[1], $m[4] );
		}
		try {
			$date = new DateTimeImmutable( $value, wp_timezone() );
		} catch ( Exception $e ) {
			return null;
		}
		return $date->getTimestamp();
	}

	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}

	private static function upper( $text ) {
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text ) : strtoupper( $text );
	}

	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : count( self::chars( $text ) );
	}

	private static function substr( $text, $start, $length = null ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, $start, $length );
		}
		return implode( '', array_slice( self::chars( $text ), $start, $length ) );
	}

	/**
	 * UTF-8 characters of a text (fallback when mbstring is not available).
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private static function chars( $text ) {
		$chars = preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
		return false === $chars ? str_split( (string) $text ) : $chars;
	}
}
