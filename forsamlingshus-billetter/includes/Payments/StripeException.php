<?php
/**
 * Stripe API error.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Raised when Stripe rejects a call. The message is safe to store, never a secret key.
 */
final class StripeException extends \RuntimeException {

	public function __construct( string $message, int $status = 0, public string $stripe_code = '' ) {
		parent::__construct( $message, $status );
	}
}
