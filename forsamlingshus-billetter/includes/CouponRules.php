<?php
/**
 * Coupon price rules. No database and no WordPress calls.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Percent off the order, the first N tickets free, or the whole order free.
 */
final class CouponRules {

	public const PERCENT = 'percent';

	public const TICKETS = 'tickets';

	public const FREE = 'free';

	public static function normalize( string $code ): string {
		$code = strtoupper( trim( $code ) );
		$compact = preg_replace( '/\s+/', '', $code );

		return is_string( $compact ) ? $compact : '';
	}

	public static function valid_code( string $code ): bool {
		return 1 === preg_match( '/^[A-Z0-9][A-Z0-9_-]{1,39}$/', $code );
	}

	/**
	 * @return array{ok:bool,error:string}
	 */
	public static function accept( bool $found, bool $active, string $expires_on, string $today, int $coupon_event_id, int $event_id, int $max_uses, int $uses ): array {
		if ( ! $found ) {
			return array(
				'ok' => false,
				'error' => 'invalid',
			);
		}
		if ( ! $active ) {
			return array(
				'ok' => false,
				'error' => 'inactive',
			);
		}
		if ( '' !== $expires_on && $today > $expires_on ) {
			return array(
				'ok' => false,
				'error' => 'expired',
			);
		}
		if ( $coupon_event_id > 0 && $coupon_event_id !== $event_id ) {
			return array(
				'ok' => false,
				'error' => 'wrong_event',
			);
		}
		if ( $max_uses > 0 && $uses >= $max_uses ) {
			return array(
				'ok' => false,
				'error' => 'used_up',
			);
		}

		return array(
			'ok' => true,
			'error' => '',
		);
	}

	/**
	 * @return array{payable_ore:int,discount_ore:int,gross_ore:int}
	 */
	public static function price( int $unit_ore, int $qty, string $type, int $percent, int $free_tickets ): array {
		$unit_ore = max( 0, $unit_ore );
		$qty = max( 0, $qty );
		$gross = $unit_ore * $qty;

		if ( self::FREE === $type ) {
			return array(
				'payable_ore' => 0,
				'discount_ore' => $gross,
				'gross_ore' => $gross,
			);
		}

		if ( self::TICKETS === $type ) {
			$free = min( $qty, max( 0, $free_tickets ) );
			$discount = $free * $unit_ore;

			return array(
				'payable_ore' => $gross - $discount,
				'discount_ore' => $discount,
				'gross_ore' => $gross,
			);
		}

		$percent = max( 0, min( 100, $percent ) );
		$discount = intdiv( ( $gross * $percent ) + 50, 100 );
		if ( $discount > $gross ) {
			$discount = $gross;
		}

		return array(
			'payable_ore' => $gross - $discount,
			'discount_ore' => $discount,
			'gross_ore' => $gross,
		);
	}

	public static function message( string $error ): string {
		return match ( $error ) {
			'invalid' => 'Koden blev ikke genkendt.',
			'inactive' => 'Koden er ikke aktiv.',
			'expired' => 'Koden er udløbet.',
			'used_up' => 'Koden er brugt op.',
			'wrong_event' => 'Koden gælder ikke dette arrangement.',
			default => 'Koden kan ikke bruges.',
		};
	}
}
