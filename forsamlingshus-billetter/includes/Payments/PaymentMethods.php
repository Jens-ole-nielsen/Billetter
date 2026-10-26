<?php
/**
 * Registry of payment methods.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Looks up Stripe, invoice, and any method added from the fhb_register_payment_methods action.
 */
final class PaymentMethods {

	/**
	 * @var array<string, PaymentMethod>
	 */
	private array $methods = array();

	public function register( PaymentMethod $method ): void {
		$this->methods[ $method->id() ] = $method;
	}

	public function get( string $id ): ?PaymentMethod {
		return $this->methods[ $id ] ?? null;
	}

	/**
	 * @param array<string, mixed> $event
	 * @return list<PaymentMethod>
	 */
	public function available_for( array $event ): array {
		$available = array();
		foreach ( $this->methods as $method ) {
			if ( $method->is_available( $event ) ) {
				$available[] = $method;
			}
		}

		return $available;
	}

	/**
	 * Stripe is always offered on a paid event, even when the secret key is missing.
	 * Invoice is included only when the event toggle is on. Stripe stays first, so a
	 * form with one hidden field cannot silently post invoice.
	 *
	 * @param list<string> $registered
	 * @return list<string>
	 */
	public static function choose_ids( array $registered, int $price_ore, bool $invoice_on ): array {
		if ( $price_ore <= 0 ) {
			return array();
		}

		$ids = array();
		if ( in_array( 'stripe', $registered, true ) ) {
			$ids[] = 'stripe';
		}
		foreach ( $registered as $id ) {
			if ( 'stripe' === $id ) {
				continue;
			}
			if ( 'invoice' === $id ) {
				if ( $invoice_on ) {
					$ids[] = 'invoice';
				}
				continue;
			}
			$ids[] = $id;
		}

		return $ids;
	}

	/**
	 * @param array<string, mixed> $event
	 * @return list<PaymentMethod>
	 */
	public function for_checkout( array $event ): array {
		$registered = array_keys( $this->methods );
		$ids = self::choose_ids( $registered, (int) ( $event['price_ore'] ?? 0 ), ! empty( $event['invoice'] ) );
		$chosen = array();
		foreach ( $ids as $id ) {
			$method = $this->get( $id );
			if ( $method ) {
				$chosen[] = $method;
			}
		}

		return $chosen;
	}
}
