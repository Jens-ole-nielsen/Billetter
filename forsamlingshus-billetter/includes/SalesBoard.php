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
 * The phone page lives in wp-admin, so it uses the WordPress login.
 */
final class SalesBoard {

	public const PAGE = 'fhb-salg';

	public const RECENT = 15;

	public static function url_path(): string {
		return 'wp-admin/admin.php?page=' . self::PAGE;
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
