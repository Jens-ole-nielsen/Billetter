<?php
/**
 * Danish kroner stored as øre.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Parse and format DKK amounts. The database stores øre as integers.
 */
final class Money {

	/**
	 * Parse an admin-entered price into øre.
	 *
	 * Accepts "125", "125,50", "1.250,50" and "125.50".
	 */
	public static function parse_ore( string $input ): ?int {
		$input = trim( $input );
		$collapsed = preg_replace( '/\s+/u', '', $input );
		$input = is_string( $collapsed ) ? $collapsed : '';
		$stripped = preg_replace( '/(kr\.?|dkk)$/iu', '', $input );
		$input = is_string( $stripped ) ? $stripped : '';

		if ( '' === $input ) {
			return null;
		}

		if ( 1 === preg_match( '/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $input ) ) {
			$input = str_replace( '.', '', $input );
			$input = str_replace( ',', '.', $input );
		} elseif ( 1 === preg_match( '/^\d+(,\d{1,2})$/', $input ) ) {
			$input = str_replace( ',', '.', $input );
		} elseif ( 1 !== preg_match( '/^\d+(\.\d{1,2})?$/', $input ) ) {
			return null;
		}

		if ( ! is_numeric( $input ) ) {
			return null;
		}

		$value = (float) $input;
		if ( $value < 0 || $value > 1000000 ) {
			return null;
		}

		return (int) round( $value * 100 );
	}

	public static function format_ore( int $ore ): string {
		$negative = $ore < 0;
		$ore = abs( $ore );
		$kroner = intdiv( $ore, 100 );
		$cents = $ore % 100;
		$formatted = number_format( $kroner, 0, ',', '.' ) . ',' . str_pad( (string) $cents, 2, '0', STR_PAD_LEFT ) . ' kr.';

		return $negative ? '-' . $formatted : $formatted;
	}
}
