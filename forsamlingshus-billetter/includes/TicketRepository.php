<?php
/**
 * Ticket rows, one per seat.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Tickets are created only after the order is paid. The buyer name stays on the order.
 */
final class TicketRepository {

	public function ensure_for_order( object $order ): void {
		$order_id = (int) $order->id;
		$qty = (int) $order->quantity;
		$have = $this->sequences( $order_id );
		for ( $sequence = 1; $sequence <= $qty; $sequence++ ) {
			if ( isset( $have[ $sequence ] ) ) {
				continue;
			}
			$this->insert( $order_id, (int) $order->event_id, $sequence );
		}
	}

	/**
	 * @return array<int, true>
	 */
	public function sequences( int $order_id ): array {
		global $wpdb;

		$values = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT sequence_no FROM ' . Database::tickets_table() . ' WHERE order_id = %d',
				$order_id
			)
		);
		$set = array();
		if ( is_array( $values ) ) {
			foreach ( $values as $value ) {
				$set[ (int) $value ] = true;
			}
		}

		return $set;
	}

	public function insert( int $order_id, int $event_id, int $sequence ): int {
		global $wpdb;

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$token = Token::generate();
			$ok = $wpdb->insert(
				Database::tickets_table(),
				array(
					'order_id' => $order_id,
					'event_id' => $event_id,
					'token' => $token,
					'sequence_no' => $sequence,
					'status' => 'valid',
					'checked_in_by' => 0,
					'created_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%d', '%s', '%d', '%s' )
			);
			if ( false !== $ok ) {
				return (int) $wpdb->insert_id;
			}
		}

		return 0;
	}

	public function find_by_token( string $token ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Database::tickets_table() . ' WHERE token = %s',
				$token
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	/**
	 * @return list<object>
	 */
	public function for_order( int $order_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Database::tickets_table() . ' WHERE order_id = %d ORDER BY sequence_no ASC',
				$order_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	public function count_with_status( int $order_id, string $status ): int {
		global $wpdb;

		if ( ! in_array( $status, array( 'valid', 'checked_in', 'cancelled' ), true ) ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . Database::tickets_table() . ' WHERE order_id = %d AND status = %s',
				$order_id,
				$status
			)
		);
	}

	/**
	 * Locks matching tickets for this transaction. Call after START TRANSACTION.
	 *
	 * @param list<string> $statuses
	 * @return list<int>
	 */
	public function lock_for_cancel( int $order_id, array $statuses, ?int $limit ): array {
		global $wpdb;

		$allowed = array();
		foreach ( $statuses as $status ) {
			if ( in_array( $status, array( 'valid', 'checked_in' ), true ) ) {
				$allowed[] = $status;
			}
		}
		if ( array() === $allowed ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $allowed ), '%s' ) );
		$sql = 'SELECT id FROM ' . Database::tickets_table() . " WHERE order_id = %d AND status IN ({$placeholders}) ORDER BY sequence_no ASC";
		$params = array_merge( array( $order_id ), $allowed );
		if ( null !== $limit ) {
			$sql .= ' LIMIT %d';
			$params[] = max( 0, $limit );
		}
		$sql .= ' FOR UPDATE';
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$params ) );
		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_map(
			static function ( $id ): int {
				return (int) $id;
			},
			$ids
		);
	}

	/**
	 * @param list<int>    $ids
	 * @param list<string> $from
	 */
	public function cancel_ids( array $ids, array $from ): int {
		global $wpdb;

		$ids = array_values(
			array_filter(
				array_map(
					static function ( $id ): int {
						return (int) $id;
					},
					$ids
				)
			)
		);
		$from = array_values(
			array_filter(
				$from,
				static function ( string $status ): bool {
					return in_array( $status, array( 'valid', 'checked_in' ), true );
				}
			)
		);
		if ( array() === $ids || array() === $from ) {
			return 0;
		}

		$id_marks = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$status_marks = implode( ', ', array_fill( 0, count( $from ), '%s' ) );
		$sql = 'UPDATE ' . Database::tickets_table() . " SET status = 'cancelled' WHERE id IN ({$id_marks}) AND status IN ({$status_marks})";
		$updated = $wpdb->query( $wpdb->prepare( $sql, ...array_merge( $ids, $from ) ) );

		return is_int( $updated ) ? $updated : 0;
	}

	public function mark_checked_in( int $ticket_id, int $user_id ): bool {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Database::tickets_table() . " SET status = 'checked_in', checked_in_at = %s, checked_in_by = %d WHERE id = %d AND status = 'valid'",
				current_time( 'mysql' ),
				$user_id,
				$ticket_id
			)
		);

		return 1 === $updated;
	}

	/**
	 * @return array{rows:list<object>,total:int}
	 */
	public function admin_list( int $event_id, int $limit, int $offset ): array {
		global $wpdb;

		$tickets = Database::tickets_table();
		$orders = Database::orders_table();
		$where = '1=1';
		$params = array();
		if ( $event_id > 0 ) {
			$where = 't.event_id = %d';
			$params[] = $event_id;
		}

		$count_sql = "SELECT COUNT(*) FROM {$tickets} t WHERE {$where}";
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) : $wpdb->get_var( $count_sql ) );

		$list_sql = "SELECT t.id, t.order_id, t.event_id, t.sequence_no, t.status, t.checked_in_at, t.token,
			o.buyer_name, o.buyer_email, o.buyer_phone, o.remark, o.quantity
			FROM {$tickets} t
			INNER JOIN {$orders} o ON o.id = t.order_id
			WHERE {$where}
			ORDER BY t.id DESC
			LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $limit, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, ...$list_params ) );

		return array(
			'rows' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}
}
