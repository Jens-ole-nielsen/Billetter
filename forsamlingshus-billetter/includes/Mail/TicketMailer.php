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
use Forsamlingshus\Billetter\Pdf\HtmlTicketPdf;
use Forsamlingshus\Billetter\Pdf\TicketPdf;
use Forsamlingshus\Billetter\Settings;
use Forsamlingshus\Billetter\TicketContext;
use Forsamlingshus\Billetter\TicketFrame;
use Forsamlingshus\Billetter\TicketRepository;
use Forsamlingshus\Billetter\TicketShortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Sends one PDF with every ticket in the order.
 */
final class TicketMailer {

	public function __construct(
		private OrderRepository $orders,
		private TicketRepository $tickets,
		private Design $design,
		private Settings $settings
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
		$tickets = $this->ticket_list( $ticket_rows );
		$binary = $this->pdf_binary( $tickets, $context );
		if ( null === $binary ) {
			return false;
		}
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
		$subject = apply_filters( 'fhb_ticket_email_subject', $subject, $order );
		$message = $this->message( $order, $context, $tickets );

		$sent = wp_mail(
			$email,
			$subject,
			$message['body'],
			$message['headers'],
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
	 * The PDF that was, or would be, attached to the ticket email.
	 */
	public function pdf_for_order( object $order ): ?string {
		$ticket_rows = $this->tickets->for_order( (int) $order->id );
		if ( array() === $ticket_rows ) {
			return null;
		}
		$event = Events::get( (int) $order->event_id );

		return $this->pdf_binary( $this->ticket_list( $ticket_rows ), $this->context( $order, $event ) );
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
			'remark' => trim( (string) $order->remark ),
			'amount' => Money::format_ore( (int) $order->amount_ore ),
			'quantity' => (string) (int) $order->quantity,
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
			'poster_id' => $event ? (int) $event['poster_id'] : 0,
			'ticket_image_id' => $event ? (int) ( $event['ticket_image_id'] ?? 0 ) : 0,
		);
	}

	/**
	 * @param list<object> $ticket_rows
	 * @return list<array{token:string,sequence:int}>
	 */
	private function ticket_list( array $ticket_rows ): array {
		$tickets = array();
		foreach ( $ticket_rows as $row ) {
			$tickets[] = array(
				'token' => (string) $row->token,
				'sequence' => (int) $row->sequence_no,
			);
		}

		return $tickets;
	}

	/**
	 * @param list<array{token:string,sequence:int}> $tickets
	 * @param array<string, mixed>                   $context
	 */
	private function pdf_binary( array $tickets, array $context ): ?string {
		$template_id = (int) $this->design->get()['elementor_template_id'];
		if ( $template_id > 0 ) {
			$html_pdf = ( new HtmlTicketPdf() )->render( $template_id, $tickets, $context );
			if ( is_string( $html_pdf ) ) {
				return $html_pdf;
			}
		}

		$image_id = TicketFrame::image_id( (int) ( $context['ticket_image_id'] ?? 0 ), (int) ( $context['poster_id'] ?? 0 ) );
		$binary = ( new TicketPdf() )->render_order( $tickets, $context, $this->logo(), Images::jpeg_cover( $image_id ) );

		return str_starts_with( $binary, '%PDF' ) ? $binary : null;
	}

	/**
	 * @param list<array{token:string,sequence:int}> $tickets
	 * @param array<string, mixed>                   $context
	 * @return array{body:string,headers:list<string>}
	 */
	private function message( object $order, array $context, array $tickets ): array {
		$first = $tickets[0]['token'] ?? '';
		$total = count( $tickets );
		TicketContext::set(
			array(
				'event' => (string) $context['event'],
				'date' => (string) $context['date'],
				'time' => (string) $context['time'],
				'buyer' => (string) $context['buyer'],
				'price' => (string) $context['price'],
				'remark' => (string) $context['remark'],
				'amount' => (string) $context['amount'],
				'quantity' => (string) $context['quantity'],
				'order_number' => (string) $context['order_number'],
				'token' => $first,
				'sequence_label' => $total > 0 ? sprintf(
					/* translators: 1: ticket number, 2: tickets in the order */
					__( 'Billet %1$d af %2$d', 'forsamlingshus-billetter' ),
					1,
					$total
				) : '',
			)
		);
		$html = ( new MailPoetBody() )->html( $this->settings->mailpoet_newsletter_id() );
		TicketContext::clear();

		if ( is_string( $html ) ) {
			$html = TicketShortcodes::replace_qr( $html, $first );
			$body = apply_filters( 'fhb_ticket_email_body', $html, $order );

			return array(
				'body' => is_string( $body ) ? $body : $html,
				'headers' => array( 'Content-Type: text/html; charset=UTF-8' ),
			);
		}

		$remark = (string) $context['remark'];
		$remark_line = '' !== $remark ? "\nBemærkning: {$remark}\n" : "\n";
		$plain = sprintf(
			"Hej %s\n\nTak for din bestilling. Dine billetter til %s ligger i den vedhæftede PDF.\nDer er én side pr. billet. Vis QR-koden ved indgangen.\n\nArrangement: %s\nDato: %s\nTidspunkt: %s\nAntal: %d\nBeløb: %s\nOrdre: %s\n%sVenlig hilsen\n%s\n",
			(string) $order->buyer_name,
			(string) $context['event'],
			(string) $context['event'],
			(string) $context['date'],
			(string) $context['time'],
			(int) $order->quantity,
			(string) $context['amount'],
			(string) $context['order_number'],
			$remark_line,
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body = apply_filters( 'fhb_ticket_email_body', $plain, $order );

		return array(
			'body' => is_string( $body ) ? $body : $plain,
			'headers' => array( 'Content-Type: text/plain; charset=UTF-8' ),
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
