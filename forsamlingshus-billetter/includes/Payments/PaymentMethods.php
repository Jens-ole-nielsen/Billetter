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
}
