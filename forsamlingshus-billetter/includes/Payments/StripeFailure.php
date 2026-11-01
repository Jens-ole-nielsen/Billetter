<?php
/**
 * Buyer-facing Stripe errors.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a Stripe error into a short code the sales page can show in Danish.
 * The Stripe code itself is logged, never the secret key.
 */
final class StripeFailure {

	/**
	 * @return 'payment_key_missing'|'payment_key'|'payment_min'|'payment_url'|'payment'
	 */
	public static function buyer_code( string $message, string $stripe_code, int $http_status ): string {
		$code = strtolower( $stripe_code );
		$text = strtolower( $message );

		if ( str_contains( $message, 'Stripe-nøglen mangler' ) || 'api_key_missing' === $code ) {
			return 'payment_key_missing';
		}
		if (
			401 === $http_status
			|| in_array( $code, array( 'api_key_expired', 'invalid_api_key', 'secret_key_required' ), true )
			|| str_contains( $text, 'invalid api key' )
			|| str_contains( $text, 'expired api key' )
		) {
			return 'payment_key';
		}
		if ( 'amount_too_small' === $code || str_contains( $text, 'amount must be at least' ) ) {
			return 'payment_min';
		}
		if ( 'url_invalid' === $code || str_contains( $text, 'not a valid url' ) ) {
			return 'payment_url';
		}

		return 'payment';
	}

	public static function log_line( string $stripe_code, int $http_status ): string {
		$code = '' !== $stripe_code ? $stripe_code : 'none';

		return 'fhb stripe checkout code=' . $code . ' http=' . $http_status;
	}

	public static function admin_note( string $stripe_code, string $message ): string {
		$code = '' !== $stripe_code ? $stripe_code : 'none';
		$note = $code . ': ' . $message;

		return mb_substr( $note, 0, 240 );
	}
}
