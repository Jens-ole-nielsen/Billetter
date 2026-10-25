<?php
/**
 * Ticket and invoice emails.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Mail;

use Forsamlingshus\Billetter\Design;
use Forsamlingshus\Billetter\Events;
use Forsamlingshus\Billetter\Format;
use Forsamlingshus\Billetter\Images;
use Forsamlingshus\Billetter\Money;
use Forsamlingshus\Billetter\OrderNumber;
use Forsamlingshus\Billetter\OrderRepository;
use Forsamlingshus\Billetter\Pdf\TicketPdf;
use Forsamlingshus\Billetter\TicketRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Sends one PDF with every ticket in the order.
 */
final class TicketMailer {

	public function __construct(
		private OrderRepository $orders,
		private TicketRepository $tickets,
		private Design $design
	) {
	}

	public function send_tickets( object $order, bool $force = false ): bool {
		if ( ! $force && ! empty( $order->tickets_sent_at ) ) {
			return true;
		}
		$email = (string) $order->buyer_email;
		if ( ! is_email( $email ) ) {
			return false;
		}

		$this->tickets->ensure_for_order( $order );
		$ticket_rows = $this->tickets->for_order( (int) $order->id );
		if ( array() === $ticket_rows ) {
			return false;
		}

		$event = Events::get( (int) $order->event_id );
		$context = $this->context( $order, $event );
		$logo = $this->logo();
		$tickets = array();
		foreach ( $ticket_rows as $row ) {
			$tickets[] = array(
				'token' => (string) $row->token,
				'sequence' => (int) $row->sequence_no,
			);
		}

		$binary = ( new TicketPdf() )->render_order( $tickets, $context, $logo );
		$path = $this->temp_pdf( $binary );
		if ( null === $path ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: event name */
			__( 'Dine billetter: %s', 'forsamlingshus-billetter' ),
			(string) $context['event']
		);
		$subject = str_replace( array( "\r", "\n" ), '', $subject );
		$remark = trim( (string) $order->remark );
		$remark_line = '' !== $remark ? "\nBemærkning: {$remark}\n" : "\n";
		$body = sprintf(
			"Hej %s\n\nTak for din bestilling. Dine billetter til %s ligger i den vedhæftede PDF.\nDer er én side pr. billet. Vis QR-koden ved indgangen.\n\nArrangement: %s\nDato: %s\nTidspunkt: %s\nAntal: %d\nBeløb: %s\nOrdre: %s\n%sVenlig hilsen\n%s\n",
			(string) $order->buyer_name,
			(string) $context['event'],
			(string) $context['event'],
			(string) $context['date'],
			(string) $context['time'],
			(int) $order->quantity,
			Money::format_ore( (int) $order->amount_ore ),
			(string) $context['order_number'],
			$remark_line,
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$subject = apply_filters( 'fhb_ticket_email_subject', $subject, $order );
		$body = apply_filters( 'fhb_ticket_email_body', $body, $order );

		$sent = wp_mail(
			$email,
			$subject,
			$body,
			array( 'Content-Type: text/plain; charset=UTF-8' ),
			array( $path )
		);
		wp_delete_file( $path );

		if ( $sent ) {
			$this->orders->touch_sent( (int) $order->id );
		}

		return (bool) $sent;
	}

	public function send_invoice_notice( object $order ): bool {
		$email = (string) $order->buyer_email;
		if ( ! is_email( $email ) ) {
			return false;
		}
		$event = Events::get( (int) $order->event_id );
		$title = $event ? (string) $event['title'] : __( 'Arrangement', 'forsamlingshus-billetter' );
		$subject = sprintf(
			/* translators: %s: event name */
			__( 'Bestilling modtaget: %s', 'forsamlingshus-billetter' ),
			$title
		);
		$subject = str_replace( array( "\r", "\n" ), '', $subject );
		$quantity = (int) $order->quantity;
		$tickets_word = 1 === $quantity ? 'billet' : 'billetter';
		$body = sprintf(
			"Hej %s\n\nVi har modtaget din bestilling af %d %s til %s.\nDu har valgt betaling via faktura efter aftale.\nDu får billetterne på e-mail, når betalingen er registreret.\n\nBeløb: %s\nOrdre: %s\n\nVenlig hilsen\n%s\n",
			(string) $order->buyer_name,
			$quantity,
			$tickets_word,
			$title,
			Money::format_ore( (int) $order->amount_ore ),
			OrderNumber::format( (int) $order->id ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		return (bool) wp_mail(
			$email,
			$subject,
			$body,
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/**
	 * @param array<string, mixed>|null $event
	 * @return array<string, mixed>
	 */
	private function context( object $order, ?array $event ): array {
		$design = $this->design->get();
		$title = $event ? (string) $event['title'] : __( 'Arrangement', 'forsamlingshus-billetter' );
		$date = $event ? Format::date( (string) $event['date'] ) : '';
		$time = $event ? Format::time( (string) $event['time'] ) : '';
		$unit = $event ? (int) $event['price_ore'] : 0;

		return array(
			'heading' => (string) $design['heading'],
			'event' => $title,
			'date' => $date,
			'time' => $time,
			'buyer' => (string) $order->buyer_name,
			'price' => Money::format_ore( $unit ),
			'order_number' => OrderNumber::format( (int) $order->id ),
			'primary' => (string) $design['color_primary'],
			'text' => (string) $design['color_text'],
			'background' => (string) $design['color_background'],
			'show_event' => ! empty( $design['show_event'] ),
			'show_date' => ! empty( $design['show_date'] ),
			'show_time' => ! empty( $design['show_time'] ),
			'show_buyer' => ! empty( $design['show_buyer'] ),
			'show_price' => ! empty( $design['show_price'] ),
			'show_qr' => ! empty( $design['show_qr'] ),
		);
	}

	/**
	 * @return array{data:string,w:int,h:int}|null
	 */
	private function logo(): ?array {
		$design = $this->design->get();

		return Images::jpeg_from_attachment( (int) $design['logo_id'], 600 );
	}

	private function temp_pdf( string $binary ): ?string {
		$dir = get_temp_dir();
		if ( ! is_string( $dir ) || '' === $dir ) {
			return null;
		}
		$path = trailingslashit( $dir ) . 'fhb-' . wp_generate_password( 16, false, false ) . '.pdf';
		$written = file_put_contents( $path, $binary );
		if ( false === $written ) {
			return null;
		}

		return $path;
	}
}
