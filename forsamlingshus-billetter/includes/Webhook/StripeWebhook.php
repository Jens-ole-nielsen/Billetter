<?php
/**
 * Stripe webhook endpoint.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Webhook;

use Forsamlingshus\Billetter\Fulfillment;
use Forsamlingshus\Billetter\OrderRepository;
use Forsamlingshus\Billetter\OrderStatus;
use Forsamlingshus\Billetter\Settings;
use Forsamlingshus\Billetter\StripeSignature;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Marks an order paid only after the signature and the amount have been checked.
 */
final class StripeWebhook {

	public function __construct(
		private Settings $settings,
		private OrderRepository $orders,
		private Fulfillment $fulfillment
	) {
	}

	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	public function register(): void {
		register_rest_route(
			'forsamlingshus-billetter/v1',
			'/stripe-webhook',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$payload = $request->get_body();
		$header_value = $request->get_header( 'stripe_signature' );
		$header = is_string( $header_value ) ? $header_value : '';
		$secret = $this->settings->webhook_secret();
		if ( ! StripeSignature::verify( $payload, $header, $secret, time() ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_signature' ), 400 );
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_json' ), 400 );
		}

		$type = isset( $event['type'] ) && is_string( $event['type'] ) ? $event['type'] : '';
		$object = $event['data']['object'] ?? null;
		if ( ! is_array( $object ) ) {
			return new WP_REST_Response( array( 'received' => true ), 200 );
		}

		$order = $this->order_from_session( $object );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'received' => true ), 200 );
		}

		$session_id = isset( $object['id'] ) && is_string( $object['id'] ) ? $object['id'] : '';
		if ( '' !== (string) $order->stripe_session_id && $session_id !== (string) $order->stripe_session_id ) {
			return new WP_REST_Response( array( 'received' => true ), 200 );
		}

		if ( 'checkout.session.completed' === $type || 'checkout.session.async_payment_succeeded' === $type ) {
			if ( ( $object['payment_status'] ?? '' ) === 'paid' ) {
				$amount = isset( $object['amount_total'] ) ? (int) $object['amount_total'] : -1;
				$currency = strtolower( (string) ( $object['currency'] ?? '' ) );
				if ( $amount !== (int) $order->amount_ore || 'dkk' !== $currency ) {
					$this->orders->set_failure( (int) $order->id, 'Stripe-beløbet matcher ikke ordren.' );
					return new WP_REST_Response( array( 'received' => true ), 200 );
				}
				$intent = $object['payment_intent'] ?? '';
				$this->fulfillment->mark_paid( (int) $order->id, is_string( $intent ) ? $intent : '' );
			}
			return new WP_REST_Response( array( 'received' => true ), 200 );
		}

		if ( 'checkout.session.expired' === $type || 'checkout.session.async_payment_failed' === $type ) {
			if ( OrderStatus::PENDING === (string) $order->status ) {
				$this->fulfillment->cancel_unpaid( (int) $order->id, false );
			}
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * @param array<string, mixed> $session
	 */
	private function order_from_session( array $session ): ?object {
		$order_id = 0;
		if ( isset( $session['metadata'] ) && is_array( $session['metadata'] ) && isset( $session['metadata']['order_id'] ) ) {
			$order_id = (int) $session['metadata']['order_id'];
		}
		if ( $order_id < 1 && isset( $session['client_reference_id'] ) ) {
			$order_id = (int) $session['client_reference_id'];
		}
		if ( $order_id < 1 ) {
			return null;
		}

		return $this->orders->find( $order_id );
	}
}
