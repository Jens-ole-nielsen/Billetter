<?php
/**
 * One PDF page per ticket.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Pdf;

use Forsamlingshus\Billetter\Color;
use Forsamlingshus\Billetter\QrCode;
use Forsamlingshus\Billetter\Token;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the designed ticket. Callers pass already formatted Danish strings.
 */
final class TicketPdf {

	/**
	 * @param list<array{token:string,sequence:int}> $tickets
	 * @param array<string, mixed>                   $context
	 * @param array{data:string,w:int,h:int}|null    $logo
	 * @param array{data:string,w:int,h:int}|null    $background
	 */
	public function render_order( array $tickets, array $context, ?array $logo = null, ?array $background = null ): string {
		$doc = new Document();
		$total = max( 1, count( $tickets ) );
		foreach ( $tickets as $ticket ) {
			$this->draw( $doc, $ticket, $total, $context, $logo, $background );
		}

		return $doc->output();
	}

	/**
	 * @param array{token:string,sequence:int}    $ticket
	 * @param array<string, mixed>                $context
	 * @param array{data:string,w:int,h:int}|null $logo
	 * @param array{data:string,w:int,h:int}|null $background
	 */
	private function draw( Document $doc, array $ticket, int $total, array $context, ?array $logo, ?array $background ): void {
		$doc->add_page();
		$primary = Color::rgb( (string) ( $context['primary'] ?? '#1F4B3A' ), array( 31, 75, 58 ) );
		$text = Color::rgb( (string) ( $context['text'] ?? '#1C1C1C' ), array( 28, 28, 28 ) );
		$paper = Color::rgb( (string) ( $context['background'] ?? '#FFFBF5' ), array( 255, 251, 245 ) );
		$photo = is_array( $background ) && isset( $background['data'], $background['w'], $background['h'] ) && is_string( $background['data'] );

		if ( $photo ) {
			$doc->jpeg( 0, 0, Document::PAGE_WIDTH, Document::PAGE_HEIGHT, $background['data'], (int) $background['w'], (int) $background['h'] );
		} else {
			$doc->fill_rgb( $paper[0], $paper[1], $paper[2] );
			$doc->rect( 0, 0, Document::PAGE_WIDTH, Document::PAGE_HEIGHT, 'F' );
		}

		$logo_w = 0.0;
		if ( is_array( $logo ) && isset( $logo['data'], $logo['w'], $logo['h'] ) && is_string( $logo['data'] ) ) {
			$logo_w = $this->place_logo( $doc, $logo );
		}

		$doc->fill_rgb( $primary[0], $primary[1], $primary[2] );
		$heading = (string) ( $context['heading'] ?? 'Adgangsbillet' );
		$heading_width = Document::PAGE_WIDTH - 96 - ( $logo_w > 0 ? $logo_w + 16 : 0 );
		$y = $doc->paragraph( 48, 52, 22, $heading, max( 180, $heading_width ), 26, true );
		$y = max( $y + 16, $logo_w > 0 ? 120 : $y + 8 );
		$fields = array(
			'show_event' => array( 'Arrangement', (string) ( $context['event'] ?? '' ) ),
			'show_date' => array( 'Dato', (string) ( $context['date'] ?? '' ) ),
			'show_time' => array( 'Tidspunkt', (string) ( $context['time'] ?? '' ) ),
			'show_buyer' => array( 'Køber', (string) ( $context['buyer'] ?? '' ) ),
			'show_price' => array( 'Pris', (string) ( $context['price'] ?? '' ) ),
		);

		foreach ( $fields as $flag => $pair ) {
			if ( empty( $context[ $flag ] ) || '' === $pair[1] ) {
				continue;
			}
			$doc->fill_rgb( $primary[0], $primary[1], $primary[2] );
			$doc->text( 48, $y, 11, $pair[0], true );
			$doc->fill_rgb( $text[0], $text[1], $text[2] );
			$y = $doc->paragraph( 48, $y + 20, 18, $pair[1], 500, 22, true );
			$y += 8;
		}

		$sequence = max( 1, (int) $ticket['sequence'] );
		$ticket_label = 'Billet ' . $sequence . ' af ' . $total;
		$code = Token::grouped( (string) $ticket['token'] );

		if ( ! empty( $context['show_qr'] ) ) {
			$qr_size = 210.0;
			$pad = 16.0;
			$qr_x = ( Document::PAGE_WIDTH - $qr_size ) / 2;
			$qr_y = max( $y + 18, 460 );
			if ( $qr_y + $qr_size > 740 ) {
				$qr_y = 490;
			}
			$doc->fill_rgb( 255, 255, 255 );
			$doc->rect( $qr_x - $pad, $qr_y - $pad, $qr_size + ( $pad * 2 ), $qr_size + 64, 'F' );
			$matrix = QrCode::matrix( Token::normalize( (string) $ticket['token'] ) );
			$doc->qr( $qr_x, $qr_y, $qr_size, $matrix );
			$doc->fill_rgb( 28, 28, 28 );
			$this->centered( $doc, $qr_y + $qr_size + 22, 14, $ticket_label, true );
			$this->centered( $doc, $qr_y + $qr_size + 42, 11, $code, false );
		} else {
			$doc->fill_rgb( $text[0], $text[1], $text[2] );
			$this->centered( $doc, max( $y + 40, 560 ), 16, $ticket_label, true );
			$this->centered( $doc, max( $y + 68, 588 ), 13, $code, false );
		}

		$footer = trim( (string) ( $context['order_number'] ?? '' ) . '  ·  Vis koden ved indgangen' );
		if ( $photo ) {
			$doc->fill_rgb( 255, 255, 255 );
			$doc->rect( 36, 792, Document::PAGE_WIDTH - 72, 32, 'F' );
			$doc->fill_rgb( 28, 28, 28 );
		} else {
			$doc->fill_rgb( $primary[0], $primary[1], $primary[2] );
			$doc->rect( 48, 786, Document::PAGE_WIDTH - 96, 1, 'F' );
			$doc->fill_rgb( $text[0], $text[1], $text[2] );
		}
		$doc->text( 48, 812, 10, $footer, false );
	}

	/**
	 * Draws the logo on the ticket and returns the width it occupied.
	 *
	 * @param array{data:string,w:int,h:int} $logo
	 */
	private function place_logo( Document $doc, array $logo ): float {
		$px_w = max( 1, (int) $logo['w'] );
		$px_h = max( 1, (int) $logo['h'] );
		$max_w = 110.0;
		$max_h = 72.0;
		$scale = min( $max_w / $px_w, $max_h / $px_h );
		$w = $px_w * $scale;
		$h = $px_h * $scale;
		$x = Document::PAGE_WIDTH - 36 - $w;
		$doc->jpeg( $x, 32, $w, $h, $logo['data'], $px_w, $px_h );

		return $w;
	}

	private function centered( Document $doc, float $baseline, float $size, string $text, bool $bold ): void {
		$width = $doc->text_width( $text, $size, $bold );
		$x = max( 36, ( Document::PAGE_WIDTH - $width ) / 2 );
		$doc->text( $x, $baseline, $size, $text, $bold );
	}
}
