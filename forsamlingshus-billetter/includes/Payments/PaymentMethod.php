<?php
/**
 * Payment method contract.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Stripe and invoice are the two built-in methods. Another plugin can register its own.
 */
interface PaymentMethod {

	public function id(): string;

	public function label(): string;

	public function description(): string;

	/**
	 * @param array<string, mixed> $event
	 */
	public function is_available( array $event ): bool;

	/**
	 * @param array<string, mixed> $order
	 * @param array<string, mixed> $event
	 * @return array{redirect?:string}
	 */
	public function begin( array $order, array $event ): array;
}
