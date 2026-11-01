<?php
/**
 * Pay-by-agreement invoice.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Invoice does not charge anything here. Tickets are sent only after an admin marks the order paid.
 */
final class InvoiceGateway implements PaymentMethod {

	public function id(): string {
		return 'invoice';
	}

	public function label(): string {
		return __( 'Betaling via faktura (efter aftale)', 'forsamlingshus-billetter' );
	}

	public function description(): string {
		return __( 'I har aftalt betalingen med forsamlingshuset. Billetterne sendes først, når betalingen er registreret.', 'forsamlingshus-billetter' );
	}

	public function is_available( array $event ): bool {
		return ! empty( $event['invoice'] ) && (int) $event['price_ore'] > 0;
	}

	public function begin( array $order, array $event ): array {
		return array();
	}
}
