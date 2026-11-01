<?php
/**
 * Shortcodes for the ticket template and the ticket email.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * The QR shortcode prints a placeholder. The PDF and mail steps swap in an image
 * afterwards, so a text filter cannot strip the picture.
 */
final class TicketShortcodes {

	public const QR_PLACEHOLDER = '[[FHB_QR]]';

	public function hooks(): void {
		$map = array(
			'fhb_event_name' => 'event',
			'fhb_date' => 'date',
			'fhb_time' => 'time',
			'fhb_buyer' => 'buyer',
			'fhb_price' => 'price',
			'fhb_remark' => 'remark',
			'fhb_order' => 'order_number',
			'fhb_quantity' => 'quantity',
			'fhb_amount' => 'amount',
			'fhb_sequence' => 'sequence_label',
		);
		foreach ( $map as $tag => $key ) {
			add_shortcode(
				$tag,
				static function () use ( $key ): string {
					return esc_html( TicketContext::get( $key ) );
				}
			);
		}
		add_shortcode(
			'fhb_qr',
			static function (): string {
				return '' === TicketContext::get( 'token' ) ? '' : self::QR_PLACEHOLDER;
			}
		);
	}

	public static function replace_qr( string $html, string $token ): string {
		return str_replace( self::QR_PLACEHOLDER, self::qr_markup( $token ), $html );
	}

	public static function qr_markup( string $token ): string {
		if ( '' === $token ) {
			return '';
		}
		$png = QrCode::png( $token );
		if ( '' === $png ) {
			return '';
		}

		return '<img src="data:image/png;base64,' . base64_encode( $png ) . '" width="180" height="180" alt="">';
	}
}
