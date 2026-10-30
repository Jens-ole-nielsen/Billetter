<?php
/**
 * Pure capacity math shared by the stock table and the tests.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * How many tickets can still be reserved for an event.
 */
final class Capacity {

	/**
	 * Tickets that are neither sold nor held.
	 */
	public static function remaining( int $capacity, int $reserved, int $sold ): int {
		return max( 0, $capacity - $reserved - $sold );
	}

	/**
	 * True when a reservation of $qty fits in the remaining stock.
	 */
	public static function can_reserve( int $capacity, int $reserved, int $sold, int $qty ): bool {
		return $qty > 0 && $qty <= self::remaining( $capacity, $reserved, $sold );
	}
}
