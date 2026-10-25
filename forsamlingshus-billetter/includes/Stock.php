<?php
/**
 * Per-event ticket stock.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Reserves seats with one conditional UPDATE.
 *
 * InnoDB locks the stock row for that statement, so two buyers cannot both
 * take the last tickets. The WHERE clause is the same rule as Capacity::can_reserve().
 */
final class Stock {

	public function sync( int $event_id, int $capacity ): bool {
		global $wpdb;

		$capacity = max( 0, $capacity );
		$table = Database::stock_table();
		$row = $this->get( $event_id );
		if ( ! $row ) {
			$inserted = $wpdb->insert(
				$table,
				array(
					'event_id' => $event_id,
					'capacity' => $capacity,
					'reserved' => 0,
					'sold' => 0,
				),
				array( '%d', '%d', '%d', '%d' )
			);

			return false !== $inserted;
		}

		if ( $capacity < ( (int) $row->reserved + (int) $row->sold ) ) {
			return false;
		}

		$wpdb->update(
			$table,
			array( 'capacity' => $capacity ),
			array( 'event_id' => $event_id ),
			array( '%d' ),
			array( '%d' )
		);

		return true;
	}

	public function get( int $event_id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT event_id, capacity, reserved, sold FROM ' . Database::stock_table() . ' WHERE event_id = %d',
				$event_id
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	public function remaining( int $event_id ): int {
		$row = $this->get( $event_id );
		if ( ! $row ) {
			return 0;
		}

		return Capacity::remaining( (int) $row->capacity, (int) $row->reserved, (int) $row->sold );
	}

	/**
	 * Paid tickets only.
	 *
	 * Checkout puts both Stripe and invoice orders in `reserved`. `sold` increases
	 * in commit_sale(), which runs when the order becomes paid. An unpaid invoice
	 * is therefore not counted here.
	 */
	public function sold( int $event_id ): int {
		$row = $this->get( $event_id );
		if ( ! $row ) {
			return 0;
		}

		return max( 0, (int) $row->sold );
	}

	public function reserve( int $event_id, int $qty ): bool {
		global $wpdb;

		if ( $qty < 1 ) {
			return false;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Database::stock_table() . ' SET reserved = reserved + %d WHERE event_id = %d AND (capacity - reserved - sold) >= %d',
				$qty,
				$event_id,
				$qty
			)
		);

		return 1 === $updated;
	}

	public function release( int $event_id, int $qty ): void {
		global $wpdb;

		if ( $qty < 1 ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Database::stock_table() . ' SET reserved = IF(reserved >= %d, reserved - %d, 0) WHERE event_id = %d',
				$qty,
				$qty,
				$event_id
			)
		);
	}

	public function commit_sale( int $event_id, int $qty ): void {
		global $wpdb;

		if ( $qty < 1 ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Database::stock_table() . ' SET reserved = IF(reserved >= %d, reserved - %d, 0), sold = sold + %d WHERE event_id = %d',
				$qty,
				$qty,
				$qty,
				$event_id
			)
		);
	}
}
