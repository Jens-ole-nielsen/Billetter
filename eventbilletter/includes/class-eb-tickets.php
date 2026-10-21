<?php
/**
 * Ticket type helpers for events.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Tickets
 */
class EB_Tickets {

	/**
	 * Get ticket types for an event.
	 *
	 * @param int $event_id Event post ID.
	 * @return array[]
	 */
	public static function get_types( $event_id ) {
		$types = get_post_meta( $event_id, '_eb_ticket_types', true );
		if ( ! is_array( $types ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $types as $type ) {
			$id = isset( $type['id'] ) ? sanitize_key( $type['id'] ) : '';
			if ( ! $id ) {
				continue;
			}
			$normalized[] = array(
				'id'          => $id,
				'name'        => isset( $type['name'] ) ? (string) $type['name'] : '',
				'description' => isset( $type['description'] ) ? (string) $type['description'] : '',
				'price'       => isset( $type['price'] ) ? (float) $type['price'] : 0.0,
				'capacity'    => isset( $type['capacity'] ) ? (int) $type['capacity'] : 0,
				'sold'        => isset( $type['sold'] ) ? (int) $type['sold'] : 0,
				'active'      => ! empty( $type['active'] ),
			);
		}

		return $normalized;
	}

	/**
	 * Save ticket types for an event.
	 *
	 * @param int   $event_id Event post ID.
	 * @param array $types    Ticket types.
	 */
	public static function save_types( $event_id, array $types ) {
		$existing = self::get_types( $event_id );
		$sold_map = array();
		foreach ( $existing as $type ) {
			$sold_map[ $type['id'] ] = (int) $type['sold'];
		}

		$clean = array();
		foreach ( $types as $type ) {
			$name = isset( $type['name'] ) ? sanitize_text_field( $type['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			$id = isset( $type['id'] ) ? sanitize_key( $type['id'] ) : '';
			if ( ! $id ) {
				$id = self::generate_id( $name );
			}

			$capacity = isset( $type['capacity'] ) ? max( 0, (int) $type['capacity'] ) : 0;
			$sold     = isset( $sold_map[ $id ] ) ? $sold_map[ $id ] : 0;

			$clean[] = array(
				'id'          => $id,
				'name'        => $name,
				'description' => isset( $type['description'] ) ? sanitize_textarea_field( $type['description'] ) : '',
				'price'       => isset( $type['price'] ) ? max( 0, round( (float) $type['price'], 2 ) ) : 0.0,
				'capacity'    => $capacity,
				'sold'        => $sold,
				'active'      => ! empty( $type['active'] ),
			);
		}

		update_post_meta( $event_id, '_eb_ticket_types', $clean );
	}

	/**
	 * Find a ticket type by ID.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $type_id  Ticket type ID.
	 * @return array|null
	 */
	public static function get_type( $event_id, $type_id ) {
		foreach ( self::get_types( $event_id ) as $type ) {
			if ( $type['id'] === $type_id ) {
				return $type;
			}
		}
		return null;
	}

	/**
	 * Remaining capacity for a type (0 capacity = unlimited).
	 *
	 * @param array $type Ticket type.
	 * @return int|-1  Remaining seats, or -1 for unlimited.
	 */
	public static function remaining( array $type ) {
		if ( (int) $type['capacity'] <= 0 ) {
			return -1;
		}
		return max( 0, (int) $type['capacity'] - (int) $type['sold'] );
	}

	/**
	 * Whether quantity can be purchased.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $type_id  Type ID.
	 * @param int    $qty      Quantity.
	 * @return true|WP_Error
	 */
	public static function can_purchase( $event_id, $type_id, $qty ) {
		$type = self::get_type( $event_id, $type_id );
		if ( ! $type || empty( $type['active'] ) ) {
			return new WP_Error( 'eb_invalid_ticket', __( 'Billettypen findes ikke eller er ikke aktiv.', 'eventbilletter' ) );
		}

		$qty = (int) $qty;
		if ( $qty < 1 ) {
			return new WP_Error( 'eb_invalid_qty', __( 'Angiv mindst 1 billet.', 'eventbilletter' ) );
		}

		$remaining = self::remaining( $type );
		if ( -1 !== $remaining && $qty > $remaining ) {
			return new WP_Error(
				'eb_sold_out',
				sprintf(
					/* translators: %d: remaining tickets */
					__( 'Der er kun %d billetter tilbage af denne type.', 'eventbilletter' ),
					$remaining
				)
			);
		}

		return true;
	}

	/**
	 * Atomically reserve capacity (increment sold).
	 *
	 * @param int    $event_id Event ID.
	 * @param string $type_id  Type ID.
	 * @param int    $qty      Quantity.
	 * @return true|WP_Error
	 */
	public static function reserve( $event_id, $type_id, $qty ) {
		$lock_key = 'eb_reserve_' . $event_id;
		$tries    = 0;

		while ( $tries < 5 ) {
			if ( get_transient( $lock_key ) ) {
				usleep( 100000 );
				++$tries;
				continue;
			}
			set_transient( $lock_key, 1, 10 );
			break;
		}

		try {
			$check = self::can_purchase( $event_id, $type_id, $qty );
			if ( is_wp_error( $check ) ) {
				return $check;
			}

			$types = self::get_types( $event_id );
			foreach ( $types as &$type ) {
				if ( $type['id'] === $type_id ) {
					$type['sold'] = (int) $type['sold'] + (int) $qty;
					break;
				}
			}
			unset( $type );

			update_post_meta( $event_id, '_eb_ticket_types', $types );
			return true;
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Release reserved capacity (decrement sold).
	 *
	 * @param int    $event_id Event ID.
	 * @param string $type_id  Type ID.
	 * @param int    $qty      Quantity.
	 */
	public static function release( $event_id, $type_id, $qty ) {
		$types = self::get_types( $event_id );
		foreach ( $types as &$type ) {
			if ( $type['id'] === $type_id ) {
				$type['sold'] = max( 0, (int) $type['sold'] - (int) $qty );
				break;
			}
		}
		unset( $type );
		update_post_meta( $event_id, '_eb_ticket_types', $types );
	}

	/**
	 * Generate a unique ticket type ID.
	 *
	 * @param string $name Name seed.
	 * @return string
	 */
	public static function generate_id( $name ) {
		$base = sanitize_key( $name );
		if ( ! $base ) {
			$base = 'ticket';
		}
		return $base . '_' . wp_generate_password( 6, false, false );
	}

	/**
	 * Generate unique ticket codes for an order line.
	 *
	 * @param int $count Number of codes.
	 * @return string[]
	 */
	public static function generate_codes( $count ) {
		$codes = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$codes[] = strtoupper( wp_generate_password( 10, false, false ) );
		}
		return $codes;
	}
}
