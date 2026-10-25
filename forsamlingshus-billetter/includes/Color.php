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

	/**
	 * Keeps the ink when it can be read on the surface. Otherwise picks a dark
	 * or light ink. The designer preview uses the same rule.
	 *
	 * @param array{0:int,1:int,2:int} $ink
	 * @param array{0:int,1:int,2:int} $surface
	 * @return array{0:int,1:int,2:int}
	 */
	public static function on_surface( array $ink, array $surface ): array {
		$ink_luma = self::luma( $ink );
		$surface_luma = self::luma( $surface );
		if ( abs( $ink_luma - $surface_luma ) >= 0.45 ) {
			return $ink;
		}

		return $surface_luma > 0.55 ? array( 28, 28, 28 ) : array( 255, 255, 255 );
	}

	/**
	 * @param array{0:int,1:int,2:int} $rgb
	 */
	public static function hex( array $rgb ): string {
		return sprintf( '#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2] );
	}

	/**
	 * @param array{0:int,1:int,2:int} $rgb
	 */
	private static function luma( array $rgb ): float {
		return ( ( 0.2126 * $rgb[0] ) + ( 0.7152 * $rgb[1] ) + ( 0.0722 * $rgb[2] ) ) / 255;
	}
}
