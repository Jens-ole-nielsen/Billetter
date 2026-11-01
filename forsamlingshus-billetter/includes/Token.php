<?php
/**
 * Unguessable ticket and order tokens.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * 128-bit hex tokens. The QR code contains the raw token.
 */
final class Token {

	public const LENGTH = 32;

	public static function generate(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	public static function normalize( string $token ): string {
		$token = strtolower( $token );
		$stripped = preg_replace( '/\s+/', '', $token );

		return is_string( $stripped ) ? $stripped : '';
	}

	public static function is_valid_format( string $token ): bool {
		return 1 === preg_match( '/^[a-f0-9]{32}$/', $token );
	}

	/**
	 * Groups of four, for the ticket and for manual entry.
	 */
	public static function grouped( string $token ): string {
		$token = self::normalize( $token );
		$grouped = trim( chunk_split( $token, 4, ' ' ) );

		return $grouped;
	}
}
