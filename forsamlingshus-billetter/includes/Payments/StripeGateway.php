<?php
/**
 * Stripe Checkout.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Payments;

use Forsamlingshus\Billetter\OrderRepository;
use Forsamlingshus\Billetter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Hosted Checkout with automatic payment methods, so Danish buyers get the methods enabled in Stripe (cards, MobilePay, and the rest).
 */
final class StripeGateway implements PaymentMethod {

	public const MIN_ORE = 250;

	public function __construct(
		private Settings $settings,
		private OrderRepository $orders
	) {
	}

	public function id(): string {
		return 'stripe';
	}

	public function label(): string {
		return __( 'Kort eller MobilePay', 'forsamlingshus-billetter' );
	}

	public function description(): string {
		return __( 'Du sendes videre til Stripe. Der kan du betale med de metoder, som er slået til — typisk kort og MobilePay.', 'forsamlingshus-billetter' );
	}

	public function is_available( array $event ): bool {
		return $this->settings->stripe_ready() && (int) $event['price_ore'] > 0;
	}

	public function begin( array $order, array $event ): array {
		$client = new StripeClient( $this->settings->secret_key() );
		$name = mb_substr( wp_strip_all_tags( (string) $event['title'] ), 0, 120 );
		if ( '' === $name ) {
			$name = 'Billet';
		}

		$session = $client->post(
			'checkout/sessions',
			array(
				'mode' => 'payment',
				'success_url' => $this->return_url( $event, 'success', (string) $order['public_token'] ),
				'cancel_url' => $this->return_url( $event, 'cancel', (string) $order['public_token'] ),
				'customer_email' => (string) $order['buyer_email'],
				'client_reference_id' => (string) $order['id'],
				'locale' => 'da',
				'automatic_payment_methods[enabled]' => 'true',
				'expires_at' => (string) ( time() + ( 30 * MINUTE_IN_SECONDS ) ),
				'metadata[order_id]' => (string) $order['id'],
				'metadata[event_id]' => (string) $event['id'],
				'line_items[0][quantity]' => (string) $order['quantity'],
				'line_items[0][price_data][currency]' => 'dkk',
				'line_items[0][price_data][unit_amount]' => (string) $event['price_ore'],
				'line_items[0][price_data][product_data][name]' => $name,
			),
			'fhb-order-' . (int) $order['id']
		);

		$session_id = isset( $session['id'] ) && is_string( $session['id'] ) ? $session['id'] : '';
		$url = isset( $session['url'] ) && is_string( $session['url'] ) ? $session['url'] : '';
		if ( '' !== $session_id ) {
			$this->orders->set_stripe_session( (int) $order['id'], $session_id );
		}
		if ( '' === $session_id || '' === $url || ! $this->is_checkout_url( $url ) ) {
			throw new StripeException( 'Stripe returnerede ikke et betalingslink.' );
		}

		return array( 'redirect' => $url );
	}

	/**
	 * @param array<string, mixed> $event
	 */
	private function return_url( array $event, string $result, string $token ): string {
		$page_id = (int) ( $event['sales_page_id'] ?? 0 );
		$base = $page_id > 0 ? get_permalink( $page_id ) : '';
		if ( ! is_string( $base ) || '' === $base ) {
			$base = home_url( '/' );
		}

		return add_query_arg(
			array(
				'fhb_result' => $result,
				'order' => $token,
			),
			$base
		);
	}

	private function is_checkout_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		return 'https' === ( $parts['scheme'] ?? '' ) && 'checkout.stripe.com' === ( $parts['host'] ?? '' );
	}
}
