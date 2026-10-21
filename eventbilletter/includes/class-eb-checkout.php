<?php
/**
 * Checkout AJAX flow → Stripe Checkout Session.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Checkout
 */
class EB_Checkout {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_eb_create_checkout', array( $this, 'ajax_create_checkout' ) );
		add_action( 'wp_ajax_nopriv_eb_create_checkout', array( $this, 'ajax_create_checkout' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_success' ) );
	}

	/**
	 * AJAX: create pending order + Stripe session.
	 */
	public function ajax_create_checkout() {
		check_ajax_referer( 'eb_checkout', 'nonce' );

		if ( ! EB_Stripe::is_configured() ) {
			wp_send_json_error(
				array( 'message' => __( 'Betaling er ikke konfigureret. Kontakt arrangøren.', 'eventbilletter' ) ),
				503
			);
		}

		$event_id       = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$customer_name  = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$customer_email = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
		$quantities     = isset( $_POST['quantities'] ) && is_array( $_POST['quantities'] ) ? wp_unslash( $_POST['quantities'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$event = get_post( $event_id );
		if ( ! $event || 'eb_event' !== $event->post_type || 'publish' !== $event->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Eventet findes ikke.', 'eventbilletter' ) ), 404 );
		}

		if ( '' === $customer_name || ! is_email( $customer_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Udfyld navn og en gyldig e-mail.', 'eventbilletter' ) ), 400 );
		}

		$sales_open = get_post_meta( $event_id, '_eb_sales_open', true );
		if ( '0' === (string) $sales_open ) {
			wp_send_json_error( array( 'message' => __( 'Billetsalg er lukket for dette event.', 'eventbilletter' ) ), 403 );
		}

		$line_items = array();
		$total      = 0.0;

		foreach ( $quantities as $type_id => $qty ) {
			$type_id = sanitize_key( $type_id );
			$qty     = absint( $qty );
			if ( $qty < 1 ) {
				continue;
			}

			$check = EB_Tickets::can_purchase( $event_id, $type_id, $qty );
			if ( is_wp_error( $check ) ) {
				wp_send_json_error( array( 'message' => $check->get_error_message() ), 400 );
			}

			$type = EB_Tickets::get_type( $event_id, $type_id );
			$line_items[] = array(
				'type_id'   => $type_id,
				'type_name' => $type['name'],
				'unit_price'=> (float) $type['price'],
				'quantity'  => $qty,
				'line_total'=> round( (float) $type['price'] * $qty, 2 ),
			);
			$total += round( (float) $type['price'] * $qty, 2 );
		}

		if ( empty( $line_items ) ) {
			wp_send_json_error( array( 'message' => __( 'Vælg mindst én billet.', 'eventbilletter' ) ), 400 );
		}

		// Reserve capacity before creating Stripe session.
		foreach ( $line_items as $item ) {
			$reserved = EB_Tickets::reserve( $event_id, $item['type_id'], $item['quantity'] );
			if ( is_wp_error( $reserved ) ) {
				// Roll back previous reservations in this request.
				foreach ( $line_items as $prev ) {
					if ( $prev['type_id'] === $item['type_id'] ) {
						break;
					}
					EB_Tickets::release( $event_id, $prev['type_id'], $prev['quantity'] );
				}
				wp_send_json_error( array( 'message' => $reserved->get_error_message() ), 409 );
			}
		}

		$currency = eventbilletter()->get_setting( 'currency', 'dkk' );

		$order_id = EB_Orders::create(
			array(
				'event_id'       => $event_id,
				'customer_name'  => $customer_name,
				'customer_email' => $customer_email,
				'line_items'     => $line_items,
				'total'          => $total,
				'currency'       => $currency,
			)
		);

		if ( is_wp_error( $order_id ) ) {
			foreach ( $line_items as $item ) {
				EB_Tickets::release( $event_id, $item['type_id'], $item['quantity'] );
			}
			wp_send_json_error( array( 'message' => $order_id->get_error_message() ), 500 );
		}

		$success_url = $this->build_return_url( 'success', $order_id );
		$cancel_url  = $this->build_return_url( 'cancel', $order_id );

		$stripe_line_items = array();
		foreach ( $line_items as $index => $item ) {
			$stripe_line_items[ $index ] = array(
				'quantity'   => $item['quantity'],
				'price_data' => array(
					'currency'     => $currency,
					'unit_amount'  => EB_Stripe::to_minor_units( $item['unit_price'], $currency ),
					'product_data' => array(
						'name'        => $event->post_title . ' — ' . $item['type_name'],
						'description' => sprintf(
							/* translators: %s: event title */
							__( 'Billet til %s', 'eventbilletter' ),
							$event->post_title
						),
					),
				),
			);
		}

		$session = EB_Stripe::create_checkout_session(
			array(
				'mode'                => 'payment',
				'success_url'         => $success_url,
				'cancel_url'          => $cancel_url,
				'customer_email'      => $customer_email,
				'client_reference_id' => (string) $order_id,
				'line_items'          => $stripe_line_items,
				'metadata'            => array(
					'eb_order_id'     => (string) $order_id,
					'eb_event_id'     => (string) $event_id,
					'eb_order_number' => (string) get_post_meta( $order_id, '_eb_order_number', true ),
				),
				'payment_intent_data' => array(
					'metadata' => array(
						'eb_order_id' => (string) $order_id,
					),
				),
			)
		);

		if ( is_wp_error( $session ) ) {
			EB_Orders::cancel( $order_id );
			wp_send_json_error( array( 'message' => $session->get_error_message() ), 502 );
		}

		update_post_meta( $order_id, '_eb_stripe_session_id', sanitize_text_field( $session['id'] ) );

		wp_send_json_success(
			array(
				'checkout_url' => $session['url'],
				'order_id'     => $order_id,
			)
		);
	}

	/**
	 * On success return: confirm payment if webhook hasn't yet.
	 */
	public function maybe_handle_success() {
		if ( empty( $_GET['eb_checkout'] ) || empty( $_GET['order_id'] ) || empty( $_GET['session_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$action   = sanitize_key( wp_unslash( $_GET['eb_checkout'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$order_id = absint( $_GET['order_id'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'cancel' === $action ) {
			EB_Orders::cancel( $order_id );
			return;
		}

		if ( 'success' !== $action ) {
			return;
		}

		$stored = get_post_meta( $order_id, '_eb_stripe_session_id', true );
		if ( $stored && $stored !== $session_id ) {
			return;
		}

		$session = EB_Stripe::retrieve_checkout_session( $session_id );
		if ( is_wp_error( $session ) ) {
			return;
		}

		if ( isset( $session['payment_status'] ) && 'paid' === $session['payment_status'] ) {
			$pi = '';
			if ( ! empty( $session['payment_intent'] ) && is_string( $session['payment_intent'] ) ) {
				$pi = $session['payment_intent'];
			}
			EB_Orders::mark_paid( $order_id, $pi );
		}
	}

	/**
	 * Build success/cancel URL with session placeholder.
	 *
	 * @param string $action   success|cancel.
	 * @param int    $order_id Order ID.
	 * @return string
	 */
	private function build_return_url( $action, $order_id ) {
		$page_key = 'success' === $action ? 'success_page_id' : 'cancel_page_id';
		$page_id  = (int) eventbilletter()->get_setting( $page_key, 0 );

		if ( $page_id ) {
			$base = get_permalink( $page_id );
		} else {
			$event_id = (int) get_post_meta( $order_id, '_eb_event_id', true );
			$base     = get_permalink( $event_id );
			if ( ! $base ) {
				$base = home_url( '/' );
			}
		}

		$url = add_query_arg(
			array(
				'eb_checkout' => $action,
				'order_id'    => $order_id,
			),
			$base
		);

		// Stripe replaces {CHECKOUT_SESSION_ID}.
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . 'session_id={CHECKOUT_SESSION_ID}';
	}
}
