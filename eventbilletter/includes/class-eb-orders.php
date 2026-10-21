<?php
/**
 * Order creation and status helpers.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Orders
 */
class EB_Orders {

	const STATUS_PENDING   = 'pending';
	const STATUS_PAID      = 'paid';
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_REFUNDED  = 'refunded';
	const STATUS_FAILED    = 'failed';

	/**
	 * Create a pending order.
	 *
	 * @param array $data Order data.
	 * @return int|WP_Error Order post ID.
	 */
	public static function create( array $data ) {
		$event_id = isset( $data['event_id'] ) ? (int) $data['event_id'] : 0;
		$event    = get_post( $event_id );
		if ( ! $event || 'eb_event' !== $event->post_type ) {
			return new WP_Error( 'eb_invalid_event', __( 'Ugyldigt event.', 'eventbilletter' ) );
		}

		$order_number = self::generate_order_number();

		$order_id = wp_insert_post(
			array(
				'post_type'   => 'eb_order',
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: 1: order number, 2: event title */
					__( 'Ordre %1$s — %2$s', 'eventbilletter' ),
					$order_number,
					$event->post_title
				),
			),
			true
		);

		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		$meta = array(
			'_eb_order_number'       => $order_number,
			'_eb_status'             => self::STATUS_PENDING,
			'_eb_event_id'           => $event_id,
			'_eb_customer_name'      => sanitize_text_field( $data['customer_name'] ?? '' ),
			'_eb_customer_email'     => sanitize_email( $data['customer_email'] ?? '' ),
			'_eb_line_items'         => $data['line_items'] ?? array(),
			'_eb_total'              => isset( $data['total'] ) ? (float) $data['total'] : 0.0,
			'_eb_currency'           => strtolower( sanitize_text_field( $data['currency'] ?? 'dkk' ) ),
			'_eb_stripe_session_id'  => '',
			'_eb_stripe_payment_intent' => '',
			'_eb_ticket_codes'       => array(),
			'_eb_paid_at'            => '',
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $order_id, $key, $value );
		}

		return $order_id;
	}

	/**
	 * Mark order as paid and issue ticket codes.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $payment_intent Stripe payment intent ID.
	 * @return true|WP_Error
	 */
	public static function mark_paid( $order_id, $payment_intent = '' ) {
		$status = get_post_meta( $order_id, '_eb_status', true );
		if ( self::STATUS_PAID === $status ) {
			return true;
		}

		$line_items = get_post_meta( $order_id, '_eb_line_items', true );
		if ( ! is_array( $line_items ) ) {
			$line_items = array();
		}

		$all_codes = array();
		foreach ( $line_items as $item ) {
			$qty   = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			$codes = EB_Tickets::generate_codes( $qty );
			$all_codes[] = array(
				'type_id'   => $item['type_id'] ?? '',
				'type_name' => $item['type_name'] ?? '',
				'codes'     => $codes,
			);
		}

		update_post_meta( $order_id, '_eb_status', self::STATUS_PAID );
		update_post_meta( $order_id, '_eb_ticket_codes', $all_codes );
		update_post_meta( $order_id, '_eb_paid_at', current_time( 'mysql' ) );

		if ( $payment_intent ) {
			update_post_meta( $order_id, '_eb_stripe_payment_intent', sanitize_text_field( $payment_intent ) );
		}

		EB_Emails::send_confirmation( $order_id );

		return true;
	}

	/**
	 * Cancel a pending order and release capacity.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function cancel( $order_id ) {
		$status = get_post_meta( $order_id, '_eb_status', true );
		if ( self::STATUS_PENDING !== $status ) {
			return;
		}

		$event_id   = (int) get_post_meta( $order_id, '_eb_event_id', true );
		$line_items = get_post_meta( $order_id, '_eb_line_items', true );
		if ( is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				EB_Tickets::release(
					$event_id,
					$item['type_id'] ?? '',
					(int) ( $item['quantity'] ?? 0 )
				);
			}
		}

		update_post_meta( $order_id, '_eb_status', self::STATUS_CANCELLED );
	}

	/**
	 * Find order by Stripe Checkout Session ID.
	 *
	 * @param string $session_id Session ID.
	 * @return int Order ID or 0.
	 */
	public static function find_by_session( $session_id ) {
		$q = new WP_Query(
			array(
				'post_type'      => 'eb_order',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_eb_stripe_session_id',
				'meta_value'     => $session_id,
			)
		);

		return ! empty( $q->posts ) ? (int) $q->posts[0] : 0;
	}

	/**
	 * Get order data as array.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 */
	public static function get( $order_id ) {
		$post = get_post( $order_id );
		if ( ! $post || 'eb_order' !== $post->post_type ) {
			return null;
		}

		return array(
			'id'                    => $order_id,
			'order_number'          => get_post_meta( $order_id, '_eb_order_number', true ),
			'status'                => get_post_meta( $order_id, '_eb_status', true ),
			'event_id'              => (int) get_post_meta( $order_id, '_eb_event_id', true ),
			'customer_name'         => get_post_meta( $order_id, '_eb_customer_name', true ),
			'customer_email'        => get_post_meta( $order_id, '_eb_customer_email', true ),
			'line_items'            => get_post_meta( $order_id, '_eb_line_items', true ),
			'total'                 => (float) get_post_meta( $order_id, '_eb_total', true ),
			'currency'              => get_post_meta( $order_id, '_eb_currency', true ),
			'stripe_session_id'     => get_post_meta( $order_id, '_eb_stripe_session_id', true ),
			'stripe_payment_intent' => get_post_meta( $order_id, '_eb_stripe_payment_intent', true ),
			'ticket_codes'          => get_post_meta( $order_id, '_eb_ticket_codes', true ),
			'paid_at'               => get_post_meta( $order_id, '_eb_paid_at', true ),
		);
	}

	/**
	 * Human-readable status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			self::STATUS_PENDING   => __( 'Afventer betaling', 'eventbilletter' ),
			self::STATUS_PAID      => __( 'Betalt', 'eventbilletter' ),
			self::STATUS_CANCELLED => __( 'Annulleret', 'eventbilletter' ),
			self::STATUS_REFUNDED  => __( 'Refunderet', 'eventbilletter' ),
			self::STATUS_FAILED    => __( 'Fejlet', 'eventbilletter' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Generate order number.
	 *
	 * @return string
	 */
	private static function generate_order_number() {
		return 'EB-' . strtoupper( wp_generate_password( 8, false, false ) );
	}
}
