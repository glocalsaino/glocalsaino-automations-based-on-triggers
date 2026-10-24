<?php
/**
 * Safe arithmetic expression evaluator for the Calculator action (no eval()).
 *
 * Supports numbers, + - * / % ^, parentheses, unary minus and the functions
 * round(x[, decimals]), floor(x), ceil(x), abs(x), sqrt(x), pow(x, y), min(a, b, …), max(a, b, …).
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_Calculator {

	/**
	 * Function name => [ min args, max args ] (-1 = unlimited).
	 */
	const FUNCTIONS = array(
		'round' => array( 1, 2 ),
		'floor' => array( 1, 1 ),
		'ceil'  => array( 1, 1 ),
		'abs'   => array( 1, 1 ),
		'sqrt'  => array( 1, 1 ),
		'pow'   => array( 2, 2 ),
		'min'   => array( 1, -1 ),
		'max'   => array( 1, -1 ),
	);

	const OPERATORS = array(
		'+' => array( 1, 'left' ),
		'-' => array( 1, 'left' ),
		'*' => array( 2, 'left' ),
		'/' => array( 2, 'left' ),
		'%' => array( 2, 'left' ),
		'^' => array( 4, 'right' ),
		'~' => array( 3, 'right' ), // Unary minus.
	);

	/**
	 * Evaluate an expression whose {{fields}} are replaced by numbers.
	 *
	 * @param string $expression Expression with tokens.
	 * @param array  $tokens     Token values.
	 * @return float|WP_Error
	 */
	public static function evaluate_with_tokens( $expression, array $tokens ) {
		$errors   = array();
		$resolved = GSAAT_Engine::replace_tokens(
			$expression,
			$tokens,
			static function ( $value ) use ( &$errors ) {
				$number = self::to_number( $value );
				if ( null === $number ) {
					$errors[] = $value;
					return '0';
				}
				return '(' . self::number_to_string( $number ) . ')';
			}
		);
		if ( $errors ) {
			/* translators: %s: value that is not a number. */
			return new WP_Error( 'glocalsaino_aat_nan', sprintf( __( '"%s" is not a number.', 'glocalsaino-automations-based-on-triggers' ), $errors[0] ) );
		}
		return self::evaluate( $resolved );
	}

	/**
	 * Evaluate a numeric expression.
	 *
	 * @param string $expression Expression.
	 * @return float|WP_Error
	 */
	public static function evaluate( $expression ) {
		$tokens = self::tokenize( (string) $expression );
		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}
		if ( ! $tokens ) {
			return self::error( __( 'The expression is empty.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		$rpn = self::to_rpn( $tokens );
		if ( is_wp_error( $rpn ) ) {
			return $rpn;
		}
		return self::run_rpn( $rpn );
	}

	/**
	 * Parse a human-entered number: "1.234,56", "1,234.56", "12,5", " 42 €" … Empty counts as 0.
	 *
	 * @param mixed $value Raw value.
	 * @return float|null Null when it is not a number.
	 */
	public static function to_number( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0.0;
		}
		$clean = preg_replace( '/[^0-9.,\-+eE]/', '', $value );
		if ( '' === $clean || ! preg_match( '/\d/', $clean ) ) {
			return null;
		}
		$last_dot   = strrpos( $clean, '.' );
		$last_comma = strrpos( $clean, ',' );
		if ( false !== $last_dot && false !== $last_comma ) {
			// The last separator is the decimal one.
			$clean = $last_comma > $last_dot
				? str_replace( ',', '.', str_replace( '.', '', $clean ) )
				: str_replace( ',', '', $clean );
		} elseif ( false !== $last_comma ) {
			$clean = substr_count( $clean, ',' ) > 1 ? str_replace( ',', '', $clean ) : str_replace( ',', '.', $clean );
		} elseif ( substr_count( $clean, '.' ) > 1 ) {
			$clean = str_replace( '.', '', $clean );
		}
		return is_numeric( $clean ) ? (float) $clean : null;
	}

	/**
	 * Machine-friendly number: no thousands separator, "." as decimal separator, no trailing zeros.
	 *
	 * @param float    $number   Number.
	 * @param int|null $decimals Round to this many decimals (null = up to 10).
	 * @return string
	 */
	public static function number_to_string( $number, $decimals = null ) {
		$number = null === $decimals ? round( $number, 10 ) : round( $number, $decimals );
		$text   = number_format( $number, null === $decimals ? 10 : $decimals, '.', '' );
		if ( false !== strpos( $text, '.' ) && null === $decimals ) {
			$text = rtrim( rtrim( $text, '0' ), '.' );
		}
		return '-0' === $text ? '0' : $text;
	}

	private static function tokenize( $expression ) {
		$tokens = array();
		$length = strlen( $expression );
		$i      = 0;
		while ( $i < $length ) {
			$char = $expression[ $i ];
			if ( preg_match( '/\s/', $char ) ) {
				++$i;
				continue;
			}
			if ( preg_match( '/\G(\d+(?:\.\d+)?|\.\d+)(?:[eE][+\-]?\d+)?/', $expression, $match, 0, $i ) ) {
				$tokens[] = array( 'num', (float) $match[0] );
				$i       += strlen( $match[0] );
				continue;
			}
			if ( preg_match( '/\G[a-zA-Z_]+/', $expression, $match, 0, $i ) ) {
				$name = strtolower( $match[0] );
				if ( ! isset( self::FUNCTIONS[ $name ] ) ) {
					/* translators: %s: function name. */
					return self::error( sprintf( __( 'Unknown function "%s".', 'glocalsaino-automations-based-on-triggers' ), $match[0] ) );
				}
				$tokens[] = array( 'fn', $name );
				$i       += strlen( $match[0] );
				continue;
			}
			if ( false !== strpos( '+-*/%^(),', $char ) ) {
				$tokens[] = array( 'op', $char );
				++$i;
				continue;
			}
			/* translators: %s: unexpected character. */
			return self::error( sprintf( __( 'Unexpected character "%s".', 'glocalsaino-automations-based-on-triggers' ), $char ) );
		}
		return $tokens;
	}

	/**
	 * Shunting-yard: infix tokens → reverse Polish notation.
	 *
	 * @param array $tokens Tokens.
	 * @return array|WP_Error
	 */
	private static function to_rpn( array $tokens ) {
		$output   = array();
		$stack    = array();
		$arg_cnt  = array(); // Argument counters of the open function calls.
		$previous = null;

		foreach ( $tokens as $token ) {
			list( $type, $value ) = $token;

			if ( 'num' === $type ) {
				$output[] = $token;
			} elseif ( 'fn' === $type ) {
				$stack[] = $token;
			} elseif ( '(' === $value ) {
				if ( $previous && 'fn' === $previous[0] ) {
					$arg_cnt[] = 1;
					$stack[]   = array( 'call', count( $arg_cnt ) );
				}
				$stack[] = $token;
			} elseif ( ',' === $value ) {
				while ( $stack && '(' !== end( $stack )[1] ) {
					$output[] = array_pop( $stack );
				}
				if ( ! $stack || ! $arg_cnt ) {
					return self::error( __( 'Misplaced comma.', 'glocalsaino-automations-based-on-triggers' ) );
				}
				++$arg_cnt[ count( $arg_cnt ) - 1 ];
			} elseif ( ')' === $value ) {
				while ( $stack && '(' !== end( $stack )[1] ) {
					$output[] = array_pop( $stack );
				}
				if ( ! $stack ) {
					return self::error( __( 'Unbalanced parentheses.', 'glocalsaino-automations-based-on-triggers' ) );
				}
				array_pop( $stack ); // The "(".
				if ( $stack && 'call' === end( $stack )[0] ) {
					array_pop( $stack );
					$function = array_pop( $stack );
					$args     = array_pop( $arg_cnt );
					if ( $previous && '(' === $previous[1] ) {
						$args = 0;
					}
					$output[] = array( 'fn', $function[1], $args );
				}
			} else {
				// Operator; "-" / "+" are unary at the start or after another operator / "(" / ",".
				$unary = null === $previous || ( 'op' === $previous[0] && ')' !== $previous[1] );
				if ( $unary && '+' === $value ) {
					$previous = $token;
					continue;
				}
				$op = $unary && '-' === $value ? '~' : $value;
				if ( $unary && '~' !== $op ) {
					return self::error( __( 'Misplaced operator.', 'glocalsaino-automations-based-on-triggers' ) );
				}
				list( $precedence, $assoc ) = self::OPERATORS[ $op ];
				while ( $stack ) {
					$top = end( $stack );
					if ( 'op' !== $top[0] || '(' === $top[1] ) {
						break;
					}
					list( $top_precedence ) = self::OPERATORS[ $top[1] ];
					if ( $top_precedence > $precedence || ( $top_precedence === $precedence && 'left' === $assoc ) ) {
						$output[] = array_pop( $stack );
					} else {
						break;
					}
				}
				$stack[] = array( 'op', $op );
			}
			$previous = $token;
		}

		while ( $stack ) {
			$top = array_pop( $stack );
			if ( '(' === $top[1] || 'call' === $top[0] || 'fn' === $top[0] ) {
				return self::error( __( 'Unbalanced parentheses.', 'glocalsaino-automations-based-on-triggers' ) );
			}
			$output[] = $top;
		}
		return $output;
	}

	private static function run_rpn( array $rpn ) {
		$stack = array();
		foreach ( $rpn as $token ) {
			if ( 'num' === $token[0] ) {
				$stack[] = $token[1];
				continue;
			}

			if ( 'fn' === $token[0] ) {
				list( , $name, $count ) = $token;
				list( $min, $max )     = self::FUNCTIONS[ $name ];
				if ( $count < $min || ( -1 !== $max && $count > $max ) || count( $stack ) < $count ) {
					/* translators: %s: function name. */
					return self::error( sprintf( __( 'Wrong number of values for %s().', 'glocalsaino-automations-based-on-triggers' ), $name ) );
				}
				$args   = array_splice( $stack, -$count );
				$result = self::call( $name, $args );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$stack[] = $result;
				continue;
			}

			$op = $token[1];
			if ( '~' === $op ) {
				if ( ! $stack ) {
					return self::error( __( 'Misplaced operator.', 'glocalsaino-automations-based-on-triggers' ) );
				}
				$stack[] = -array_pop( $stack );
				continue;
			}
			if ( count( $stack ) < 2 ) {
				return self::error( __( 'Misplaced operator.', 'glocalsaino-automations-based-on-triggers' ) );
			}
			$b = array_pop( $stack );
			$a = array_pop( $stack );
			if ( ( '/' === $op || '%' === $op ) && 0.0 === (float) $b ) {
				return self::error( __( 'Division by zero.', 'glocalsaino-automations-based-on-triggers' ) );
			}
			switch ( $op ) {
				case '+':
					$stack[] = $a + $b;
					break;
				case '-':
					$stack[] = $a - $b;
					break;
				case '*':
					$stack[] = $a * $b;
					break;
				case '/':
					$stack[] = $a / $b;
					break;
				case '%':
					$stack[] = fmod( $a, $b );
					break;
				case '^':
					$stack[] = pow( $a, $b );
					break;
			}
		}

		if ( 1 !== count( $stack ) ) {
			return self::error( __( 'The expression is incomplete.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		$result = (float) $stack[0];
		if ( is_nan( $result ) || is_infinite( $result ) ) {
			return self::error( __( 'The result is not a valid number.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		return $result;
	}

	private static function call( $name, array $args ) {
		switch ( $name ) {
			case 'round':
				return round( $args[0], isset( $args[1] ) ? (int) $args[1] : 0 );
			case 'floor':
				return floor( $args[0] );
			case 'ceil':
				return ceil( $args[0] );
			case 'abs':
				return abs( $args[0] );
			case 'sqrt':
				return $args[0] < 0 ? self::error( __( 'Square root of a negative number.', 'glocalsaino-automations-based-on-triggers' ) ) : sqrt( $args[0] );
			case 'pow':
				return pow( $args[0], $args[1] );
			case 'min':
				return min( $args );
			case 'max':
				return max( $args );
		}
		return self::error( __( 'Unknown function.', 'glocalsaino-automations-based-on-triggers' ) );
	}

	private static function error( $message ) {
		return new WP_Error( 'glocalsaino_aat_calc', $message );
	}
}
