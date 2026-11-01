<?php
/**
 * Values available to ticket shortcodes while a PDF or email is rendered.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * One ticket, or the order-level fields for the ticket email, at a time.
 */
final class TicketContext {

	/**
	 * @var array<string, string>|null
	 */
	private static ?array $current = null;

	/**
	 * @param array<string, scalar> $fields
	 */
	public static function set( array $fields ): void {
		$current = array();
		foreach ( $fields as $key => $value ) {
			$current[ (string) $key ] = (string) $value;
		}
		self::$current = $current;
	}

	public static function clear(): void {
		self::$current = null;
	}

	public static function get( string $key ): string {
		if ( null === self::$current || ! isset( self::$current[ $key ] ) ) {
			return '';
		}

		return self::$current[ $key ];
	}
}
