<?php
/**
 * Which events belong on the phone sales screen.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * The phone page uses the WordPress login.
 * /salg and the wp-admin address show the same board.
 */
final class SalesBoard {

	public const PAGE = 'fhb-salg';

	public const PUBLIC_SLUG = 'salg';

	public const QUERY_VAR = 'fhb_salg';

	public const ROUTE_OPTION = 'fhb_public_board';

	public const RECENT = 15;

	public static function url_path(): string {
		return 'wp-admin/admin.php?page=' . self::PAGE;
	}

	/**
	 * Pretty permalink for the phone page, for example /salg.
	 */
	public static function register_rewrite(): void {
		add_rewrite_rule( '^' . self::PUBLIC_SLUG . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * True when the request is exactly /salg, with or without a trailing slash.
	 * A site installed in a subdirectory matches that directory plus /salg.
	 */
	public static function is_public_path( string $request_uri, string $home_path = '/' ): bool {
		return self::PUBLIC_SLUG === self::relative_path( $request_uri, $home_path );
	}

	public static function relative_path( string $request_uri, string $home_path = '/' ): string {
		$request_uri = rawurldecode( $request_uri );
		foreach ( array( '?', '#' ) as $mark ) {
			$cut = strpos( $request_uri, $mark );
			if ( false !== $cut ) {
				$request_uri = substr( $request_uri, 0, $cut );
			}
		}
		$home = trim( rawurldecode( $home_path ), '/' );
		$path = trim( $request_uri, '/' );
		if ( '' !== $home ) {
			if ( $path === $home ) {
				return '';
			}
			$prefix = $home . '/';
			if ( str_starts_with( $path, $prefix ) ) {
				$path = substr( $path, strlen( $prefix ) );
			}
		}

		return strtolower( $path );
	}

	/**
	 * Keep upcoming events and the one that ended yesterday. Older dates drop off the phone.
	 */
	public static function visible( string $event_date, string $today ): bool {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $event_date ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $today ) ) {
			return true;
		}
		$start = \DateTimeImmutable::createFromFormat( '!Y-m-d', $today );
		if ( ! $start ) {
			return true;
		}

		return $event_date >= $start->modify( '-1 day' )->format( 'Y-m-d' );
	}

	/**
	 * @param list<array{sold?:int,remaining?:int,amount_ore?:int}> $events
	 * @return array{sold:int,remaining:int,amount_ore:int}
	 */
	public static function totals( array $events ): array {
		$sold = 0;
		$remaining = 0;
		$amount = 0;
		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}
			$sold += max( 0, (int) ( $event['sold'] ?? 0 ) );
			$remaining += max( 0, (int) ( $event['remaining'] ?? 0 ) );
			$amount += (int) ( $event['amount_ore'] ?? 0 );
		}

		return array(
			'sold' => $sold,
			'remaining' => $remaining,
			'amount_ore' => $amount,
		);
	}
}
