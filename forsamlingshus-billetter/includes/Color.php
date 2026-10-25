<?php
/**
 * Hex colors shared by the designer and the PDF.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Converts #rrggbb into RGB channels.
 */
final class Color {

	/**
	 * @param array{0:int,1:int,2:int} $fallback
	 * @return array{0:int,1:int,2:int}
	 */
	public static function rgb( string $hex, array $fallback = array( 0, 0, 0 ) ): array {
		$hex = ltrim( trim( $hex ), '#' );
		if ( 1 !== preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			return $fallback;
		}

		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}
}
