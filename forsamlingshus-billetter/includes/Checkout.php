<?php
/**
 * Public checkout.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

use Forsamlingshus\Billetter\Mail\TicketMailer;
use Forsamlingshus\Billetter\Payments\PaymentMethods;
use Forsamlingshus\Billetter\Payments\StripeException;
use Forsamlingshus\Billetter\Payments\StripeFailure;
use Forsamlingshus\Billetter\Payments\StripeGateway;

defined( 'ABSPATH' ) || exit;

/**
 * Reserves seats and starts Stripe, invoice, or a free order.
 */
final class Checkout {

	public const MAX_PER_ORDER = 50;

	public function __construct(
		private OrderRepository $orders,
		private Stock $stock,
		private PaymentMethods $methods,
		private Fulfillment $fulfillment,
		private TicketMailer $mailer
	) {
	}

	public function hooks(): void {
		add_action( 'admin_post_nopriv_fhb_checkout', array( $this, 'handle' ) );
		add_action( 'admin_post_fhb_checkout', array( $this, 'handle' ) );
	}

	public function handle(): void {
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$nonce = isset( $_POST['fhb_checkout_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['fhb_checkout_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'fhb_checkout_' . $event_id ) ) {
			wp_die(
				esc_html__( 'Sikkerhedstjekket fejlede. Gå tilbage og prøv igen.', 'forsamlingshus-billetter' ),
				'',
				array( 'response' => 403 )
			);
		}
		if ( ! empty( $_POST['fhb_company'] ) ) {
			$this->fail( $event_id, 'validation' );
		}

		$event = Events::get( $event_id );
		if ( ! $event || 'publish' !== $event['status'] ) {
			$this->fail( $event_id, 'closed' );
		}

		$name = isset( $_POST['buyer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['buyer_name'] ) ) : '';
		$email = isset( $_POST['buyer_email'] ) ? sanitize_email( wp_unslash( $_POST['buyer_email'] ) ) : '';
		$phone = isset( $_POST['buyer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['buyer_phone'] ) ) : '';
		$qty = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;
		$remark = isset( $_POST['remark'] ) ? sanitize_textarea_field( wp_unslash( $_POST['remark'] ) ) : '';
		$remark = mb_substr( $remark, 0, 500 );
		$method_id = isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : '';
		if ( '' === $method_id ) {
			$method_id = 'stripe';
		}

		$digits = preg_replace( '/\D+/', '', $phone );
		$digit_count = is_string( $digits ) ? strlen( $digits ) : 0;
		if ( mb_strlen( $name ) < 2 || ! is_email( $email ) || $digit_count < 8 || $digit_count > 15 ) {
			$this->fail( $event_id, 'validation' );
		}
		if ( $qty < 1 || $qty > self::MAX_PER_ORDER ) {
			$this->fail( $event_id, 'validation' );
		}

		$remaining = $this->stock->remaining( $event_id );
		if ( $qty > $remaining ) {
			$this->fail( $event_id, 'soldout' );
		}

		$unit = (int) $event['price_ore'];
		$total = $unit * $qty;
		if ( 0 === $unit ) {
			$method_id = 'free';
		} elseif ( 'invoice' === $method_id ) {
			if ( empty( $event['invoice'] ) ) {
				$this->fail( $event_id, 'method' );
			}
		} elseif ( 'stripe' === $method_id ) {
			$method = $this->methods->get( 'stripe' );
			if ( ! $method ) {
				$this->fail( $event_id, 'config' );
			}
			if ( ! $method->is_available( $event ) ) {
				$this->fail( $event_id, 'payment_key_missing' );
			}
			if ( $total < StripeGateway::MIN_ORE ) {
				$this->fail( $event_id, 'stripe_min' );
			}
		} else {
			$this->fail( $event_id, 'method' );
		}

		$status = 'invoice' === $method_id ? OrderStatus::AWAITING : OrderStatus::PENDING;
		$expires = null;
		if ( 'stripe' === $method_id ) {
			$expires = wp_date( 'Y-m-d H:i:s', time() + StripeGateway::SESSION_SECONDS );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		if ( ! $this->stock->reserve( $event_id, $qty ) ) {
			$wpdb->query( 'ROLLBACK' );
			$this->fail( $event_id, 'soldout' );
		}

		$public_token = Token::generate();
		$order_id = $this->orders->insert(
			array(
				'event_id' => $event_id,
				'buyer_name' => $name,
				'buyer_email' => $email,
				'buyer_phone' => $phone,
				'quantity' => $qty,
				'amount_ore' => $total,
				'status' => $status,
				'remark' => $remark,
				'payment_method' => $method_id,
				'public_token' => $public_token,
				'expires_at' => $expires,
			)
		);
		if ( $order_id < 1 ) {
			$wpdb->query( 'ROLLBACK' );
			$this->fail( $event_id, 'validation' );
		}
		$wpdb->query( 'COMMIT' );

		$order = $this->orders->find( $order_id );
		if ( ! $order ) {
			$this->fail( $event_id, 'validation' );
		}

		if ( 'free' === $method_id ) {
			$this->fulfillment->mark_paid( $order_id, '' );
			$this->redirect( $event, 'success', $public_token );
		}

		if ( 'invoice' === $method_id ) {
			$this->mailer->send_invoice_notice( $order );
			$this->redirect( $event, 'invoice', $public_token );
		}

		$gateway = $this->methods->get( 'stripe' );
		if ( ! $gateway ) {
			$this->fulfillment->cancel_unpaid( $order_id, false );
			$this->fail( $event_id, 'config' );
		}

		try {
			$order_data = array(
				'id' => $order_id,
				'public_token' => $public_token,
				'buyer_email' => $email,
				'quantity' => $qty,
			);
			$result = $gateway->begin( $order_data, $event );
			wp_redirect( $result['redirect'] );
			exit;
		} catch ( StripeException $exception ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Stripe code only, never the secret.
			error_log( StripeFailure::log_line( $exception->stripe_code, $exception->getCode() ) );
			$this->orders->set_failure( $order_id, StripeFailure::admin_note( $exception->stripe_code, $exception->getMessage() ) );
			$this->fulfillment->cancel_unpaid( $order_id, true );
			$this->fail( $event_id, StripeFailure::buyer_code( $exception->getMessage(), $exception->stripe_code, $exception->getCode() ) );
		}
	}

	/**
	 * @param array<string, mixed> $event
	 */
	private function redirect( array $event, string $result, string $token ): never {
		$page_id = (int) $event['sales_page_id'];
		$base = $page_id > 0 ? get_permalink( $page_id ) : '';
		if ( ! is_string( $base ) || '' === $base ) {
			$base = home_url( '/' );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'fhb_result' => $result,
					'order' => $token,
				),
				$base
			)
		);
		exit;
	}

	private function fail( int $event_id, string $code ): never {
		$event = Events::get( $event_id );
		$base = '';
		if ( $event && (int) $event['sales_page_id'] > 0 ) {
			$permalink = get_permalink( (int) $event['sales_page_id'] );
			$base = is_string( $permalink ) ? $permalink : '';
		}
		if ( '' === $base ) {
			$base = home_url( '/' );
		}
		wp_safe_redirect(
			add_query_arg(
				array( 'fhb_error' => $code ),
				$base
			)
		);
		exit;
	}
}
