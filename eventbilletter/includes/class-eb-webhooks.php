<?php
/**
 * Stripe webhook endpoint.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Webhooks
 */
class EB_Webhooks {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST route.
	 */
	public function register_routes() {
		register_rest_route(
			'eventbilletter/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle incoming Stripe events.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		$payload   = $request->get_body();
		$sig       = $request->get_header( 'stripe_signature' );
		$secret    = (string) eventbilletter()->get_setting( 'stripe_webhook_secret', '' );

		$verified = EB_Stripe::verify_webhook( $payload, (string) $sig, $secret );
		if ( is_wp_error( $verified ) ) {
			return new WP_Error( 'eb_webhook_invalid', $verified->get_error_message(), array( 'status' => 400 ) );
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['type'] ) ) {
			return new WP_Error( 'eb_webhook_bad_json', __( 'Ugyldigt webhook-payload.', 'eventbilletter' ), array( 'status' => 400 ) );
		}

		$type = $event['type'];
		$data = isset( $event['data']['object'] ) ? $event['data']['object'] : array();

		switch ( $type ) {
			case 'checkout.session.completed':
				$this->on_checkout_completed( $data );
				break;
			case 'checkout.session.expired':
				$this->on_checkout_expired( $data );
				break;
			case 'charge.refunded':
				$this->on_charge_refunded( $data );
				break;
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * checkout.session.completed
	 *
	 * @param array $session Session object.
	 */
	private function on_checkout_completed( array $session ) {
		$order_id = 0;
		if ( ! empty( $session['client_reference_id'] ) ) {
			$order_id = absint( $session['client_reference_id'] );
		} elseif ( ! empty( $session['metadata']['eb_order_id'] ) ) {
			$order_id = absint( $session['metadata']['eb_order_id'] );
		} elseif ( ! empty( $session['id'] ) ) {
			$order_id = EB_Orders::find_by_session( $session['id'] );
		}

		if ( ! $order_id ) {
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
	 * checkout.session.expired — release capacity.
	 *
	 * @param array $session Session object.
	 */
	private function on_checkout_expired( array $session ) {
		$order_id = 0;
		if ( ! empty( $session['client_reference_id'] ) ) {
			$order_id = absint( $session['client_reference_id'] );
		} elseif ( ! empty( $session['id'] ) ) {
			$order_id = EB_Orders::find_by_session( $session['id'] );
		}

		if ( $order_id ) {
			EB_Orders::cancel( $order_id );
		}
	}

	/**
	 * charge.refunded
	 *
	 * @param array $charge Charge object.
	 */
	private function on_charge_refunded( array $charge ) {
		$pi = isset( $charge['payment_intent'] ) ? (string) $charge['payment_intent'] : '';
		if ( ! $pi ) {
			return;
		}

		$q = new WP_Query(
			array(
				'post_type'      => 'eb_order',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_eb_stripe_payment_intent',
				'meta_value'     => $pi,
			)
		);

		if ( empty( $q->posts ) ) {
			return;
		}

		$order_id = (int) $q->posts[0];
		$status   = get_post_meta( $order_id, '_eb_status', true );
		if ( EB_Orders::STATUS_PAID !== $status ) {
			return;
		}

		$event_id   = (int) get_post_meta( $order_id, '_eb_event_id', true );
		$line_items = get_post_meta( $order_id, '_eb_line_items', true );
		if ( is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				EB_Tickets::release( $event_id, $item['type_id'] ?? '', (int) ( $item['quantity'] ?? 0 ) );
			}
		}

		update_post_meta( $order_id, '_eb_status', EB_Orders::STATUS_REFUNDED );
	}
}
