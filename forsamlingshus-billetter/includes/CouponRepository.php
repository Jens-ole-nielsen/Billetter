<?php
/**
 * Coupon storage.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes fhb_coupons. A use is an order that still holds the code.
 */
final class CouponRepository {

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql' );
		$expires = $data['expires_on'] ?? null;
		$expires_sql = is_string( $expires ) && '' !== $expires ? "'" . esc_sql( $expires ) . "'" : 'NULL';
		$table = Database::coupons_table();
		$prepared = $wpdb->prepare(
			"INSERT INTO {$table}
			(code, type, percent, free_tickets, event_id, max_uses, active, expires_on, created_at, updated_at)
			VALUES (%s, %s, %d, %d, %d, %d, %d, {$expires_sql}, %s, %s)",
			(string) $data['code'],
			(string) $data['type'],
			(int) $data['percent'],
			(int) $data['free_tickets'],
			(int) $data['event_id'],
			(int) $data['max_uses'],
			empty( $data['active'] ) ? 0 : 1,
			$now,
			$now
		);
		if ( ! is_string( $prepared ) || false === $wpdb->query( $prepared ) ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	public function find_by_code( string $code ): ?object {
		global $wpdb;

		$table = Database::coupons_table();
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE code = %s LIMIT 1",
				$code
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	public function lock( string $code ): ?object {
		global $wpdb;

		$table = Database::coupons_table();
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE code = %s LIMIT 1 FOR UPDATE",
				$code
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	/**
	 * @return list<object>
	 */
	public function all(): array {
		global $wpdb;

		$table = Database::coupons_table();
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );

		return is_array( $rows ) ? $rows : array();
	}

	public function set_active( int $id, bool $active ): bool {
		global $wpdb;

		$table = Database::coupons_table();
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET active = %d, updated_at = %s WHERE id = %d",
				$active ? 1 : 0,
				current_time( 'mysql' ),
				$id
			)
		);

		return false !== $updated;
	}

	public function delete( int $id ): bool {
		global $wpdb;

		$table = Database::coupons_table();
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id = %d",
				$id
			)
		);

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Orders that still hold the code. Cancelled and expired orders do not count,
	 * and deleting an event's orders releases those uses.
	 */
	public function count_uses( string $code ): int {
		global $wpdb;

		if ( '' === $code ) {
			return 0;
		}
		$table = Database::orders_table();
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE coupon_code = %s AND status IN (%s, %s, %s)",
				$code,
				OrderStatus::PENDING,
				OrderStatus::AWAITING,
				OrderStatus::PAID
			)
		);

		return (int) $count;
	}
}
