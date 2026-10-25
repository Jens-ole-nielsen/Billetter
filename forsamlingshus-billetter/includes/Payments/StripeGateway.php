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

	/**
	 * Stripe rejects expires_at when it is not strictly at least 30 minutes ahead.
	 * 35 minutes leaves room for clock skew.
	 */
	public const SESSION_SECONDS = 2100;

	public function __construct(
		private Settings $settings,
		private OrderRepository $orders
	) {
	}

	public function id(): string {
		return 'stripe';
	}

	public function label(): string {
		return __( 'Betalingskort', 'forsamlingshus-billetter' );
	}

	public function description(): string {
		return __( 'Du sendes videre til Stripe. Der betaler du med betalingskort.', 'forsamlingshus-billetter' );
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
		$quantity = (int) $order['quantity'];
		$unit = (int) $event['price_ore'];
		if ( isset( $order['amount_ore'] ) ) {
			$unit = (int) $order['amount_ore'];
			if ( $quantity > 1 ) {
				$name = mb_substr( $name . ' (' . $quantity . ' billetter)', 0, 120 );
			}
			$quantity = 1;
		}

		$session = $client->post(
			'checkout/sessions',
			self::session_fields(
				(int) $order['id'],
				(string) $order['buyer_email'],
				$quantity,
				$unit,
				$name,
				(int) $event['id'],
				$this->return_url( $event, 'success', (string) $order['public_token'] ),
				$this->return_url( $event, 'cancel', (string) $order['public_token'] ),
				time() + self::SESSION_SECONDS
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
	 * Fields for POST /v1/checkout/sessions.
	 *
	 * payment_method_types is omitted on purpose. Checkout then uses the methods
	 * turned on in the Stripe Dashboard (card, MobilePay, and the rest).
	 * automatic_payment_methods belongs to PaymentIntents and is rejected here.
	 * unit_amount is øre, which is the smallest unit for dkk.
	 *
	 * @return array<string, string>
	 */
	public static function session_fields(
		int $order_id,
		string $email,
		int $quantity,
		int $unit_ore,
		string $product_name,
		int $event_id,
		string $success_url,
		string $cancel_url,
		int $expires_at
	): array {
		return array(
			'mode' => 'payment',
			'success_url' => $success_url,
			'cancel_url' => $cancel_url,
			'customer_email' => $email,
			'client_reference_id' => (string) $order_id,
			'locale' => 'da',
			'expires_at' => (string) $expires_at,
			'metadata[order_id]' => (string) $order_id,
			'metadata[event_id]' => (string) $event_id,
			'line_items[0][quantity]' => (string) $quantity,
			'line_items[0][price_data][currency]' => 'dkk',
			'line_items[0][price_data][unit_amount]' => (string) $unit_ore,
			'line_items[0][price_data][product_data][name]' => $product_name,
		);
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
