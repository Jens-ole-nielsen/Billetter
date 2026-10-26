<?php
/**
 * Stripe webhook signature check.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies the Stripe-Signature header.
 *
 * @see https://docs.stripe.com/webhooks#verify-official-libraries
 */
final class StripeSignature {

	public static function verify( string $payload, string $header, string $secret, int $now, int $tolerance = 300 ): bool {
		if ( '' === $secret || '' === $header ) {
			return false;
		}

		$timestamp = null;
		$signatures = array();

		foreach ( explode( ',', $header ) as $part ) {
			$part = trim( $part );
			if ( str_starts_with( $part, 't=' ) ) {
				$timestamp = substr( $part, 2 );
			} elseif ( str_starts_with( $part, 'v1=' ) ) {
				$signatures[] = substr( $part, 3 );
			}
		}

		if ( null === $timestamp || array() === $signatures || 1 !== preg_match( '/^\d+$/', $timestamp ) ) {
			return false;
		}

		$stamp = (int) $timestamp;
		if ( abs( $now - $stamp ) > $tolerance ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}
}
