<?php
/**
 * Order storage.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes fhb_orders.
 */
final class OrderRepository {

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql' );
		$expires = $data['expires_at'] ?? null;
		$expires_sql = is_string( $expires ) && '' !== $expires ? "'" . esc_sql( $expires ) . "'" : 'NULL';
		$table = Database::orders_table();

		$prepared = $wpdb->prepare(
			"INSERT INTO {$table}
			(event_id, buyer_name, buyer_email, buyer_phone, quantity, amount_ore, currency, status, remark, payment_method, stripe_session_id, stripe_payment_intent, public_token, expires_at, paid_at, tickets_sent_at, failure_reason, created_at, updated_at)
			VALUES (%d, %s, %s, %s, %d, %d, %s, %s, %s, %s, '', '', %s, {$expires_sql}, NULL, NULL, '', %s, %s)",
			(int) $data['event_id'],
			(string) $data['buyer_name'],
			(string) $data['buyer_email'],
			(string) $data['buyer_phone'],
			(int) $data['quantity'],
			(int) $data['amount_ore'],
			'DKK',
			(string) $data['status'],
			(string) ( $data['remark'] ?? '' ),
			(string) $data['payment_method'],
			(string) $data['public_token'],
			$now,
			$now
		);

		if ( ! is_string( $prepared ) || false === $wpdb->query( $prepared ) ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	public function find( int $id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Database::orders_table() . ' WHERE id = %d',
				$id
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	public function find_by_public_token( string $token ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Database::orders_table() . ' WHERE public_token = %s',
				$token
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	public function lock( int $id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Database::orders_table() . ' WHERE id = %d FOR UPDATE',
				$id
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	public function apply_refund( int $id, int $refunded_ore, string $refund_ids, string $payment_intent ): bool {
		global $wpdb;

		$fields = array(
			'refunded_ore' => max( 0, $refunded_ore ),
			'stripe_refund_ids' => $refund_ids,
			'updated_at' => current_time( 'mysql' ),
		);
		$formats = array( '%d', '%s', '%s' );
		if ( 1 === preg_match( '/^pi_[A-Za-z0-9_]+$/', $payment_intent ) ) {
			$fields['stripe_payment_intent'] = $payment_intent;
			$formats[] = '%s';
		}

		$updated = $wpdb->update(
			Database::orders_table(),
			$fields,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return false !== $updated;
	}

	public function set_stripe_session( int $id, string $session_id ): void {
		global $wpdb;

		$wpdb->update(
			Database::orders_table(),
			array(
				'stripe_session_id' => $session_id,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public function set_failure( int $id, string $reason ): void {
		global $wpdb;

		$wpdb->update(
			Database::orders_table(),
			array(
				'failure_reason' => mb_substr( $reason, 0, 250 ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public function touch_sent( int $id ): void {
		global $wpdb;

		$now = current_time( 'mysql' );
		$wpdb->update(
			Database::orders_table(),
			array(
				'tickets_sent_at' => $now,
				'updated_at' => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Moves an order from one of $from into $to. Returns true only when this call won the update.
	 *
	 * @param list<string>         $from
	 * @param array<string,string> $extra
	 */
	public function transition( int $id, array $from, string $to, array $extra = array() ): bool {
		global $wpdb;

		if ( array() === $from || ! in_array( $to, OrderStatus::all(), true ) ) {
			return false;
		}

		$allowed = array( 'paid_at', 'stripe_payment_intent', 'failure_reason' );
		$sets = array( 'status = %s', 'updated_at = %s' );
		$params = array( $to, current_time( 'mysql' ) );
		foreach ( $extra as $column => $value ) {
			if ( ! in_array( $column, $allowed, true ) ) {
				continue;
			}
			$sets[] = $column . ' = %s';
			$params[] = $value;
		}
		$params[] = $id;
		foreach ( $from as $status ) {
			$params[] = $status;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $from ), '%s' ) );
		$sql = 'UPDATE ' . Database::orders_table() . ' SET ' . implode( ', ', $sets ) . " WHERE id = %d AND status IN ({$placeholders})";
		$updated = $wpdb->query( $wpdb->prepare( $sql, ...$params ) );

		return 1 === $updated;
	}

	/**
	 * @return array{rows:list<object>,total:int}
	 */
	public function query( int $event_id, string $status, string $search, int $limit, int $offset ): array {
		global $wpdb;

		$where = array( '1=1' );
		$params = array();
		if ( $event_id > 0 ) {
			$where[] = 'event_id = %d';
			$params[] = $event_id;
		}
		if ( in_array( $status, OrderStatus::all(), true ) ) {
			$where[] = 'status = %s';
			$params[] = $status;
		}
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(buyer_name LIKE %s OR buyer_email LIKE %s OR buyer_phone LIKE %s OR remark LIKE %s)';
			array_push( $params, $like, $like, $like, $like );
		}

		$sql_where = implode( ' AND ', $where );
		$table = Database::orders_table();
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}";
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) : $wpdb->get_var( $count_sql ) );

		$list_sql = "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $limit, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, ...$list_params ) );

		return array(
			'rows' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * @return list<string>
	 */
	public function paid_emails( int $event_id ): array {
		global $wpdb;

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT buyer_email FROM ' . Database::orders_table() . " WHERE event_id = %d AND status = %s AND buyer_email <> ''",
				$event_id,
				OrderStatus::PAID
			)
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $email ): string {
						return is_string( $email ) ? $email : '';
					},
					$rows
				)
			)
		);
	}

	/**
	 * Stripe checkouts whose hold has passed, with a grace period already applied by the caller.
	 *
	 * @return list<object>
	 */
	public function pending_expired( string $cutoff, int $limit ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Database::orders_table() . ' WHERE status = %s AND payment_method = %s AND expires_at IS NOT NULL AND expires_at < %s ORDER BY id ASC LIMIT %d',
				OrderStatus::PENDING,
				'stripe',
				$cutoff,
				$limit
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return list<object>
	 */
	public function by_email( string $email ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Database::orders_table() . ' WHERE buyer_email = %s',
				$email
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	public function anonymize( int $id ): void {
		global $wpdb;

		$wpdb->update(
			Database::orders_table(),
			array(
				'buyer_name' => 'Slettet',
				'buyer_email' => '',
				'buyer_phone' => '',
				'remark' => '',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}
}
