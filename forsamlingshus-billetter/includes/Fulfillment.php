<?php
/**
 * Marks orders paid, issues tickets, and releases unpaid holds.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

use Forsamlingshus\Billetter\Mail\TicketMailer;
use Forsamlingshus\Billetter\Payments\StripeClient;
use Forsamlingshus\Billetter\Payments\StripeException;

defined( 'ABSPATH' ) || exit;

/**
 * Payment is confirmed by the Stripe webhook or by an admin on an invoice order.
 */
final class Fulfillment {

	public function __construct(
		private OrderRepository $orders,
		private TicketRepository $tickets,
		private Stock $stock,
		private TicketMailer $mailer,
		private Settings $settings
	) {
	}

	/**
	 * @return array{ok:bool,mailed:bool}
	 */
	public function mark_paid( int $order_id, string $payment_intent = '' ): array {
		$order = $this->orders->find( $order_id );
		if ( ! $order ) {
			return array(
				'ok' => false,
				'mailed' => false,
			);
		}

		$extra = array( 'paid_at' => current_time( 'mysql' ) );
		if ( '' !== $payment_intent ) {
			$extra['stripe_payment_intent'] = $payment_intent;
		}
		$changed = $this->orders->transition(
			$order_id,
			array( OrderStatus::PENDING, OrderStatus::AWAITING ),
			OrderStatus::PAID,
			$extra
		);
		if ( $changed ) {
			$this->stock->commit_sale( (int) $order->event_id, (int) $order->quantity );
		} elseif ( OrderStatus::PAID !== (string) $order->status ) {
			return array(
				'ok' => false,
				'mailed' => false,
			);
		}

		$fresh = $this->orders->find( $order_id );
		if ( ! $fresh ) {
			return array(
				'ok' => false,
				'mailed' => false,
			);
		}
		$this->tickets->ensure_for_order( $fresh );

		return array(
			'ok' => true,
			'mailed' => $this->mailer->send_tickets( $fresh, false ),
		);
	}

	public function resend( int $order_id ): bool {
		$order = $this->orders->find( $order_id );
		if ( ! $order || OrderStatus::PAID !== (string) $order->status ) {
			return false;
		}
		$this->tickets->ensure_for_order( $order );

		return $this->mailer->send_tickets( $order, true );
	}

	/**
	 * Cancels an unpaid order and gives the seats back.
	 *
	 * For Stripe, the Checkout session is expired first so a late payment cannot land after the seats are released.
	 */
	public function cancel_unpaid( int $order_id, bool $try_stripe ): string {
		$order = $this->orders->find( $order_id );
		if ( ! $order ) {
			return 'missing';
		}
		if ( ! in_array( (string) $order->status, array( OrderStatus::PENDING, OrderStatus::AWAITING ), true ) ) {
			return 'unchanged';
		}

		if ( $try_stripe && 'stripe' === (string) $order->payment_method && '' !== (string) $order->stripe_session_id ) {
			$outcome = $this->reconcile_session( $order );
			if ( 'paid' === $outcome ) {
				return 'paid';
			}
			if ( 'retry' === $outcome ) {
				return 'retry';
			}
		}

		$changed = $this->orders->transition(
			$order_id,
			array( OrderStatus::PENDING, OrderStatus::AWAITING ),
			OrderStatus::CANCELLED,
			array()
		);
		if ( $changed ) {
			$this->stock->release( (int) $order->event_id, (int) $order->quantity );
			return 'cancelled';
		}

		return 'unchanged';
	}

	public function expire_due(): void {
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - 120 );
		foreach ( $this->orders->pending_expired( $cutoff, 50 ) as $order ) {
			$this->cancel_unpaid( (int) $order->id, true );
		}
	}

	private function reconcile_session( object $order ): string {
		if ( ! $this->settings->stripe_ready() ) {
			return 'retry';
		}
		$client = new StripeClient( $this->settings->secret_key() );
		$session_id = rawurlencode( (string) $order->stripe_session_id );
		try {
			$session = $client->get( 'checkout/sessions/' . $session_id );
		} catch ( StripeException $exception ) {
			$this->orders->set_failure( (int) $order->id, $exception->getMessage() );
			return 'retry';
		}

		if ( $this->session_is_paid( $session, $order ) ) {
			$intent = $session['payment_intent'] ?? '';
			$this->mark_paid( (int) $order->id, is_string( $intent ) ? $intent : '' );
			return 'paid';
		}

		$status = isset( $session['status'] ) && is_string( $session['status'] ) ? $session['status'] : '';
		if ( 'expired' === $status ) {
			return 'clear';
		}
		if ( 'open' !== $status ) {
			$this->orders->set_failure( (int) $order->id, 'Stripe-session kunne ikke lukkes.' );
			return 'retry';
		}

		try {
			$client->post( 'checkout/sessions/' . $session_id . '/expire', array() );
		} catch ( StripeException $exception ) {
			$this->orders->set_failure( (int) $order->id, $exception->getMessage() );
			return 'retry';
		}

		return 'clear';
	}

	/**
	 * @param array<string, mixed> $session
	 */
	private function session_is_paid( array $session, object $order ): bool {
		if ( ( $session['payment_status'] ?? '' ) !== 'paid' ) {
			return false;
		}
		$amount = isset( $session['amount_total'] ) ? (int) $session['amount_total'] : -1;
		$currency = strtolower( (string) ( $session['currency'] ?? '' ) );
		if ( $amount !== (int) $order->amount_ore || 'dkk' !== $currency ) {
			$this->orders->set_failure( (int) $order->id, 'Stripe-beløbet matcher ikke ordren.' );
			return false;
		}

		return true;
	}
}
