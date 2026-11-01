<?php
/**
 * Order status values.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Stored status slugs and their Danish labels.
 */
final class OrderStatus {

	public const PENDING = 'pending';

	public const AWAITING = 'awaiting_payment';

	public const PAID = 'paid';

	public const EXPIRED = 'expired';

	public const CANCELLED = 'cancelled';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::PENDING,
			self::AWAITING,
			self::PAID,
			self::EXPIRED,
			self::CANCELLED,
		);
	}

	public static function label( string $status ): string {
		return match ( $status ) {
			self::PENDING => __( 'Afventer Stripe', 'forsamlingshus-billetter' ),
			self::AWAITING => __( 'Afventer betaling', 'forsamlingshus-billetter' ),
			self::PAID => __( 'Betalt', 'forsamlingshus-billetter' ),
			self::EXPIRED => __( 'Udløbet', 'forsamlingshus-billetter' ),
			self::CANCELLED => __( 'Annulleret', 'forsamlingshus-billetter' ),
			default => $status,
		};
	}

	public static function payment_label( string $method ): string {
		return match ( $method ) {
			'stripe' => __( 'Kort / MobilePay (Stripe)', 'forsamlingshus-billetter' ),
			'invoice' => __( 'Faktura', 'forsamlingshus-billetter' ),
			'free' => __( 'Gratis', 'forsamlingshus-billetter' ),
			default => $method,
		};
	}
}
