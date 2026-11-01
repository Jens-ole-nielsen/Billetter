<?php
/**
 * Refund amount and ticket rules. No database and no Stripe call.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a refund request is allowed before anything is stored or sent.
 */
final class RefundRules {

	/**
	 * @return array{ok:bool,error:string,amount_ore:int,cancel:int,void_all:bool,settlement:string}
	 */
	public static function plan(
		string $payment_method,
		string $status,
		int $amount_ore,
		int $refunded_ore,
		int $unused_tickets,
		string $mode,
		?int $requested_ore,
		int $cancel_count,
		?int $stripe_holds_ore
	): array {
		$empty = array(
			'ok' => false,
			'error' => '',
			'amount_ore' => 0,
			'cancel' => 0,
			'void_all' => false,
			'settlement' => '',
		);

		if ( 'stripe' !== $payment_method ) {
			$empty['error'] = 'not_stripe';
			return $empty;
		}
		if ( OrderStatus::PAID !== $status ) {
			$empty['error'] = 'not_paid';
			return $empty;
		}

		$remaining = $amount_ore - max( 0, $refunded_ore );
		if ( $remaining < 1 ) {
			$empty['error'] = 'nothing_left';
			return $empty;
		}

		if ( 'full' === $mode ) {
			$amount = $remaining;
			$cancel = 0;
			$void_all = true;
		} elseif ( 'partial' === $mode ) {
			if ( null === $requested_ore || $requested_ore < 1 ) {
				$empty['error'] = 'bad_amount';
				return $empty;
			}
			if ( $requested_ore > $remaining ) {
				$empty['error'] = 'too_much';
				return $empty;
			}
			if ( $cancel_count < 0 ) {
				$empty['error'] = 'bad_count';
				return $empty;
			}
			if ( $cancel_count > $unused_tickets ) {
				$empty['error'] = 'too_many_tickets';
				return $empty;
			}
			$amount = $requested_ore;
			$cancel = $cancel_count;
			$void_all = false;
		} else {
			$empty['error'] = 'bad_mode';
			return $empty;
		}

		if ( null !== $stripe_holds_ore && $amount > $stripe_holds_ore ) {
			$empty['error'] = 'stripe_short';
			return $empty;
		}

		return array(
			'ok' => true,
			'error' => '',
			'amount_ore' => $amount,
			'cancel' => $cancel,
			'void_all' => $void_all,
			'settlement' => ( $refunded_ore + $amount ) >= $amount_ore ? 'full' : 'partial',
		);
	}

	public static function settlement( int $amount_ore, int $refunded_ore ): string {
		if ( $refunded_ore < 1 ) {
			return 'none';
		}
		if ( $refunded_ore >= $amount_ore && $amount_ore > 0 ) {
			return 'full';
		}

		return 'partial';
	}

	/**
	 * @return list<string>
	 */
	public static function ids( string $stored ): array {
		$out = array();
		foreach ( explode( ',', $stored ) as $part ) {
			$part = trim( $part );
			if ( 1 === preg_match( '/^re_[A-Za-z0-9_]+$/', $part ) ) {
				$out[] = $part;
			}
		}

		return $out;
	}

	public static function with_id( string $stored, string $id ): string {
		if ( 1 !== preg_match( '/^re_[A-Za-z0-9_]+$/', $id ) ) {
			return $stored;
		}
		$ids = self::ids( $stored );
		if ( ! in_array( $id, $ids, true ) ) {
			$ids[] = $id;
		}
		$joined = implode( ',', $ids );
		while ( strlen( $joined ) > 1000 && array() !== $ids ) {
			array_shift( $ids );
			$joined = implode( ',', $ids );
		}

		return $joined;
	}
}
