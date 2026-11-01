<?php
/**
 * Creates a Stripe refund and then updates the order.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

use Forsamlingshus\Billetter\Payments\StripeClient;
use Forsamlingshus\Billetter\Payments\StripeException;

defined( 'ABSPATH' ) || exit;

/**
 * Money is refunded on the Checkout Session payment intent. Local rows change only after Stripe accepts it.
 */
final class StripeRefund {

	public function __construct(
		private OrderRepository $orders,
		private TicketRepository $tickets,
		private Stock $stock,
		private Settings $settings
	) {
	}

	/**
	 * @return array{ok:bool,message:string}
	 */
	public function refund( int $order_id, string $mode, ?int $requested_ore, int $cancel_count ): array {
		$order = $this->orders->find( $order_id );
		if ( ! $order ) {
			return $this->fail( 'missing', 0, null );
		}

		$unused = $this->tickets->count_with_status( $order_id, 'valid' );
		$preview = $this->plan_for( $order, $unused, $mode, $requested_ore, $cancel_count, null );
		if ( ! $preview['ok'] ) {
			return $this->fail( $preview['error'], $this->remaining( $order ), null );
		}
		if ( ! $this->settings->stripe_ready() ) {
			return array(
				'ok' => false,
				'message' => __( 'Stripe-nøglen mangler, så der blev ikke refunderet. Ordren er ikke ændret.', 'forsamlingshus-billetter' ),
			);
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		try {
			$locked = $this->orders->lock( $order_id );
			if ( ! $locked ) {
				$wpdb->query( 'ROLLBACK' );
				return $this->fail( 'missing', 0, null );
			}

			$valid_ids = $this->tickets->lock_for_cancel( $order_id, array( 'valid' ), null );
			$open_ids = $preview['void_all']
				? $this->tickets->lock_for_cancel( $order_id, array( 'checked_in' ), null )
				: array();
			$plan = $this->plan_for( $locked, count( $valid_ids ), $mode, $requested_ore, $cancel_count, null );
			if ( ! $plan['ok'] ) {
				$wpdb->query( 'ROLLBACK' );
				return $this->fail( $plan['error'], $this->remaining( $locked ), null );
			}

			$chosen = $plan['void_all'] ? array_merge( $valid_ids, $open_ids ) : array_slice( $valid_ids, 0, $plan['cancel'] );
			if ( ! $plan['void_all'] && count( $chosen ) !== $plan['cancel'] ) {
				$wpdb->query( 'ROLLBACK' );
				return $this->fail( 'too_many_tickets', $this->remaining( $locked ), null );
			}

			$client = new StripeClient( $this->settings->secret_key() );
			$intent_id = $this->intent_id( $client, $locked );
			$holds = $this->stripe_holds( $client, $intent_id );
			$plan = $this->plan_for( $locked, count( $valid_ids ), $mode, $requested_ore, $cancel_count, $holds );
			if ( ! $plan['ok'] ) {
				$wpdb->query( 'ROLLBACK' );
				return $this->fail( $plan['error'], $this->remaining( $locked ), $holds );
			}

			$refund_id = $this->create_refund( $client, $locked, $intent_id, $plan['amount_ore'], $plan['void_all'], $plan['cancel'] );
			$ids = RefundRules::with_id( (string) ( $locked->stripe_refund_ids ?? '' ), $refund_id );
			$stored = $this->orders->apply_refund(
				$order_id,
				(int) ( $locked->refunded_ore ?? 0 ) + $plan['amount_ore'],
				$ids,
				$intent_id
			);
			if ( ! $stored ) {
				throw new \RuntimeException( 'Refunderingen blev ikke gemt.' );
			}
			$voided = $this->tickets->cancel_ids( $chosen, $plan['void_all'] ? array( 'valid', 'checked_in' ) : array( 'valid' ) );
			$restore = $voided;
			if ( $plan['void_all'] && 0 === $voided && array() === $chosen && 0 === $this->tickets->count_with_status( $order_id, 'cancelled' ) ) {
				$restore = (int) $locked->quantity;
			}
			if ( $restore > 0 ) {
				$this->stock->restore_sold( (int) $locked->event_id, $restore );
			}
			$wpdb->query( 'COMMIT' );
		} catch ( StripeException $exception ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'ok' => false,
				'message' => $exception->getMessage() . ' ' . __( 'Ordren er ikke ændret.', 'forsamlingshus-billetter' ),
			);
		} catch ( \Throwable $exception ) {
			$wpdb->query( 'ROLLBACK' );
			unset( $exception );
			return array(
				'ok' => false,
				'message' => __( 'Refunderingen blev ikke gemt. Tjek Stripe, før du prøver igen.', 'forsamlingshus-billetter' ),
			);
		}

		$settlement = 'full' === $plan['settlement']
			? __( 'fuldt', 'forsamlingshus-billetter' )
			: __( 'delvist', 'forsamlingshus-billetter' );

		return array(
			'ok' => true,
			'message' => sprintf(
				/* translators: 1: amount, 2: fully or partly, 3: Stripe refund id, 4: cancelled tickets */
				__( 'Stripe har refunderet %1$s (%2$s). Refund-id: %3$s. %4$d billetter er annulleret.', 'forsamlingshus-billetter' ),
				Money::format_ore( $plan['amount_ore'] ),
				$settlement,
				$refund_id,
				$voided
			),
		);
	}

	/**
	 * @return array{ok:bool,error:string,amount_ore:int,cancel:int,void_all:bool,settlement:string}
	 */
	private function plan_for( object $order, int $unused, string $mode, ?int $requested_ore, int $cancel_count, ?int $holds ): array {
		return RefundRules::plan(
			(string) $order->payment_method,
			(string) $order->status,
			(int) $order->amount_ore,
			(int) ( $order->refunded_ore ?? 0 ),
			$unused,
			$mode,
			$requested_ore,
			$cancel_count,
			$holds
		);
	}

	private function remaining( object $order ): int {
		return max( 0, (int) $order->amount_ore - (int) ( $order->refunded_ore ?? 0 ) );
	}

	/**
	 * @return array{ok:bool,message:string}
	 */
	private function fail( string $code, int $remaining, ?int $holds ): array {
		$message = match ( $code ) {
			'not_stripe' => __( 'Kun en ordre betalt med Stripe kan refunderes. Faktura refunderes ikke her.', 'forsamlingshus-billetter' ),
			'not_paid' => __( 'Kun en betalt ordre kan refunderes.', 'forsamlingshus-billetter' ),
			'nothing_left' => __( 'Der er ikke mere at refundere.', 'forsamlingshus-billetter' ),
			'bad_amount' => __( 'Skriv et beløb i kroner, for eksempel 125,50.', 'forsamlingshus-billetter' ),
			'too_much' => sprintf(
				/* translators: %s: remaining amount */
				__( 'Beløbet er højere end de %s, der stadig kan refunderes. Ordren er ikke ændret.', 'forsamlingshus-billetter' ),
				Money::format_ore( $remaining )
			),
			'bad_count', 'bad_mode' => __( 'Antallet af billetter er ugyldigt. Ordren er ikke ændret.', 'forsamlingshus-billetter' ),
			'too_many_tickets' => __( 'Der er ikke så mange ubrugte billetter på ordren. Ordren er ikke ændret.', 'forsamlingshus-billetter' ),
			'stripe_short' => sprintf(
				/* translators: %s: amount Stripe can still refund */
				__( 'Stripe har kun %s tilbage på betalingen. Ordren er ikke ændret.', 'forsamlingshus-billetter' ),
				Money::format_ore( (int) $holds )
			),
			default => __( 'Ordren blev ikke fundet.', 'forsamlingshus-billetter' ),
		};

		return array(
			'ok' => false,
			'message' => $message,
		);
	}

	private function intent_id( StripeClient $client, object $order ): string {
		$stored = (string) ( $order->stripe_payment_intent ?? '' );
		if ( 1 === preg_match( '/^pi_[A-Za-z0-9_]+$/', $stored ) ) {
			return $stored;
		}

		$session_id = (string) ( $order->stripe_session_id ?? '' );
		if ( 1 !== preg_match( '/^cs_[A-Za-z0-9_]+$/', $session_id ) ) {
			throw new StripeException( __( 'Ordren har ingen Stripe-betaling, der kan refunderes.', 'forsamlingshus-billetter' ) );
		}

		$session = $client->get( 'checkout/sessions/' . rawurlencode( $session_id ) );
		$intent = $session['payment_intent'] ?? '';
		if ( is_array( $intent ) ) {
			$intent = $intent['id'] ?? '';
		}
		if ( ! is_string( $intent ) || 1 !== preg_match( '/^pi_[A-Za-z0-9_]+$/', $intent ) ) {
			throw new StripeException( __( 'Stripe-sessionen har ingen betaling, der kan refunderes.', 'forsamlingshus-billetter' ) );
		}

		return $intent;
	}

	private function stripe_holds( StripeClient $client, string $intent_id ): int {
		$intent = $client->get( 'payment_intents/' . rawurlencode( $intent_id ) . '?expand[]=latest_charge' );
		$charge = $intent['latest_charge'] ?? null;
		if ( is_string( $charge ) && 1 === preg_match( '/^ch_[A-Za-z0-9_]+$/', $charge ) ) {
			$charge = $client->get( 'charges/' . rawurlencode( $charge ) );
		}
		if ( ! is_array( $charge ) ) {
			return 0;
		}
		$captured = isset( $charge['amount_captured'] ) ? (int) $charge['amount_captured'] : (int) ( $charge['amount'] ?? 0 );
		$refunded = (int) ( $charge['amount_refunded'] ?? 0 );

		return max( 0, $captured - $refunded );
	}

	private function create_refund( StripeClient $client, object $order, string $intent_id, int $amount_ore, bool $void_all, int $cancel ): string {
		$idempotency = sprintf(
			'fhb-refund-%d-%d-%d-%s',
			(int) $order->id,
			(int) ( $order->refunded_ore ?? 0 ),
			$amount_ore,
			$void_all ? 'all' : (string) $cancel
		);
		$refund = $client->post(
			'refunds',
			array(
				'payment_intent' => $intent_id,
				'amount' => $amount_ore,
				'reason' => 'requested_by_customer',
			),
			$idempotency
		);
		$status = isset( $refund['status'] ) && is_string( $refund['status'] ) ? $refund['status'] : '';
		if ( ! in_array( $status, array( 'succeeded', 'pending' ), true ) ) {
			throw new StripeException( __( 'Stripe afviste refunderingen.', 'forsamlingshus-billetter' ) );
		}
		$id = isset( $refund['id'] ) && is_string( $refund['id'] ) ? $refund['id'] : '';
		if ( 1 !== preg_match( '/^re_[A-Za-z0-9_]+$/', $id ) ) {
			throw new StripeException( __( 'Stripe returnerede intet refund-id.', 'forsamlingshus-billetter' ) );
		}

		return $id;
	}
}
