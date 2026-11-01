<?php
/**
 * Tells the site administrator when a payment is settled or does not go through.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Mail;

use Forsamlingshus\Billetter\Events;
use Forsamlingshus\Billetter\Money;
use Forsamlingshus\Billetter\OrderNumber;

defined( 'ABSPATH' ) || exit;

/**
 * The recipient is the WordPress administration email. There is no separate address.
 */
final class AdminNotice {

	public const PAID = 'paid';

	public const FAILED = 'failed';

	public const EXPIRED = 'expired';

	public static function recipient(): string {
		if ( ! function_exists( 'get_option' ) ) {
			return '';
		}
		$email = get_option( 'admin_email' );
		return is_string( $email ) ? trim( $email ) : '';
	}

	/**
	 * @return array{subject:string,body:string}
	 */
	public static function message( string $kind, string $event, string $buyer, int $quantity, string $amount, string $order_number ): array {
		$event = self::plain( $event );
		if ( '' === $event ) {
			$event = 'Arrangement';
		}
		$buyer = self::plain( $buyer );
		if ( '' === $buyer ) {
			$buyer = 'Ukendt køber';
		}
		$quantity = max( 0, $quantity );
		$amount = self::plain( $amount );
		$order_number = self::plain( $order_number );
		$tickets = 1 === $quantity ? 'billet' : 'billetter';

		if ( self::FAILED === $kind ) {
			$subject = 'Betaling fejlede: ' . $event;
			$lead = 'En betaling fejlede.';
		} elseif ( self::EXPIRED === $kind ) {
			$subject = 'Betaling udløb: ' . $event;
			$lead = 'En betaling udløb.';
		} else {
			$subject = 'Ordre betalt: ' . $event;
			$lead = 'En ordre er betalt.';
		}

		$body = $lead . "\n\n"
			. 'Arrangement: ' . $event . "\n"
			. 'Køber: ' . $buyer . "\n"
			. 'Antal: ' . $quantity . ' ' . $tickets . "\n"
			. 'Beløb: ' . $amount . "\n"
			. 'Ordre: ' . $order_number . "\n";

		return array(
			'subject' => $subject,
			'body' => $body,
		);
	}

	public static function send( object $order, string $kind ): bool {
		$to = self::recipient();
		if ( ! function_exists( 'is_email' ) || ! is_email( $to ) || ! function_exists( 'wp_mail' ) ) {
			return false;
		}
		$event = Events::get( (int) $order->event_id );
		$title = $event ? (string) $event['title'] : __( 'Arrangement', 'forsamlingshus-billetter' );
		$message = self::message(
			$kind,
			$title,
			(string) $order->buyer_name,
			(int) $order->quantity,
			Money::format_ore( (int) $order->amount_ore ),
			OrderNumber::format( (int) $order->id )
		);

		return (bool) wp_mail(
			$to,
			$message['subject'],
			$message['body'],
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	private static function plain( string $value ): string {
		return trim( str_replace( array( "\r", "\n" ), ' ', $value ) );
	}
}
