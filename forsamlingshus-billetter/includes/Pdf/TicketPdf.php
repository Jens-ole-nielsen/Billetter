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
use Forsamlingshus\Billetter\TicketFrame;
use Forsamlingshus\Billetter\Token;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the fixed ticket sheet. Callers pass already formatted Danish strings.
 *
 * The poster is the whole image in the top band. Text sits on the paper under
 * it, in a color that can be read there. The QR card is on the right. The
 * designer preview uses TicketFrame::sheet(), so the two pictures share the
 * rectangles.
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
		$size = TicketFrame::size( (string) ( $context['ticket_size'] ?? 'a4' ) );
		$page_w = $size['w'];
		$page_h = $size['h'];
		$doc->add_page( $page_w, $page_h );
		$primary = Color::rgb( (string) ( $context['primary'] ?? '#1F4B3A' ), array( 31, 75, 58 ) );
		$text = Color::rgb( (string) ( $context['text'] ?? '#1C1C1C' ), array( 28, 28, 28 ) );
		$paper = Color::rgb( (string) ( $context['background'] ?? '#FFFBF5' ), array( 255, 251, 245 ) );
		$photo = is_array( $background ) && isset( $background['data'], $background['w'], $background['h'] ) && is_string( $background['data'] );
		$has_logo = is_array( $logo ) && isset( $logo['data'], $logo['w'], $logo['h'] ) && is_string( $logo['data'] );
		$label_ink = Color::on_surface( $primary, $paper );
		$value_ink = Color::on_surface( $text, $paper );

		$fields = array( 'heading' );
		$rows = array(
			'event' => array( 'show_event', (string) ( $context['event'] ?? '' ) ),
			'date' => array( 'show_date', (string) ( $context['date'] ?? '' ) ),
			'time' => array( 'show_time', (string) ( $context['time'] ?? '' ) ),
			'buyer' => array( 'show_buyer', (string) ( $context['buyer'] ?? '' ) ),
			'price' => array( 'show_price', (string) ( $context['price'] ?? '' ) ),
		);
		foreach ( $rows as $name => $row ) {
			if ( ! empty( $context[ $row[0] ] ) && '' !== $row[1] ) {
				$fields[] = $name;
			}
		}
		$sheet = TicketFrame::sheet(
			$photo ? (int) $background['w'] : 0,
			$photo ? (int) $background['h'] : 0,
			$page_w,
			$page_h,
			$fields,
			$has_logo
		);
		$boxes = $sheet['blocks'];

		$doc->fill_rgb( $paper[0], $paper[1], $paper[2] );
		$doc->rect( 0, 0, $page_w, $page_h, 'F' );
		if ( $photo ) {
			$frame = $sheet['photo'];
			$doc->clipped_jpeg( $frame['x'], $frame['y'], $frame['w'], $frame['h'], $background['data'], (int) $background['w'], (int) $background['h'] );
		}
		if ( $has_logo && isset( $boxes['logo'] ) ) {
			$this->place_logo( $doc, $logo, $boxes['logo'] );
		}

		$this->place_heading(
			$doc,
			(string) ( $context['heading'] ?? 'Adgangsbillet' ),
			$boxes['heading'],
			$label_ink
		);
		$labels = array(
			'event' => 'Arrangement',
			'date' => 'Dato',
			'time' => 'Tidspunkt',
			'buyer' => 'Køber',
			'price' => 'Pris',
		);
		foreach ( $rows as $name => $row ) {
			if ( ! isset( $boxes[ $name ] ) ) {
				continue;
			}
			$this->place_field( $doc, $labels[ $name ], $row[1], $boxes[ $name ], $label_ink, $value_ink );
		}

		$sequence = max( 1, (int) $ticket['sequence'] );
		$this->place_qr(
			$doc,
			(string) $ticket['token'],
			'Billet ' . $sequence . ' af ' . $total,
			trim( (string) ( $context['order_number'] ?? '' ) ),
			$boxes['qr'],
			! empty( $context['show_qr'] ),
			$value_ink
		);
	}

	/**
	 * @param array{x:float,y:float,w:float,h:float} $rect
	 * @param array{0:int,1:int,2:int}               $ink
	 */
	private function place_heading( Document $doc, string $heading, array $rect, array $ink ): void {
		$inner = max( 20.0, $rect['w'] - 4 );
		$size = $this->fit_size( $doc, $heading, min( 18.0, $rect['h'] * 0.55 ), $inner, true );
		$doc->fill_rgb( $ink[0], $ink[1], $ink[2] );
		$doc->text( $rect['x'], $rect['y'] + $size + 1, $size, $heading, true );
	}

	/**
	 * @param array{x:float,y:float,w:float,h:float} $rect
	 * @param array{0:int,1:int,2:int}               $label_ink
	 * @param array{0:int,1:int,2:int}               $value_ink
	 */
	private function place_field( Document $doc, string $label, string $value, array $rect, array $label_ink, array $value_ink ): void {
		$inner = max( 20.0, $rect['w'] - 4 );
		$label_size = $this->fit_size( $doc, $label, min( 9.0, $rect['h'] * 0.34 ), $inner, true );
		$value_size = $this->fit_size( $doc, $value, min( 12.5, $rect['h'] * 0.42 ), $inner, true );
		$doc->fill_rgb( $label_ink[0], $label_ink[1], $label_ink[2] );
		$doc->text( $rect['x'], $rect['y'] + $label_size + 1, $label_size, $label, true );
		$doc->fill_rgb( $value_ink[0], $value_ink[1], $value_ink[2] );
		$doc->paragraph( $rect['x'], $rect['y'] + $label_size + $value_size + 3, $value_size, $value, $inner, $value_size + 1.5, true );
	}

	/**
	 * @param array{x:float,y:float,w:float,h:float} $rect
	 * @param array{0:int,1:int,2:int}               $ink Text color used when the QR code is off.
	 */
	private function place_qr( Document $doc, string $token, string $ticket_label, string $order_number, array $rect, bool $show_qr, array $ink ): void {
		$groups = preg_split( '/\s+/', trim( Token::grouped( $token ) ) );
		$groups = is_array( $groups ) ? array_values( array_filter( $groups, 'is_string' ) ) : array();
		$code = implode( ' ', $groups );
		$code_lines = array( $code );
		$notes = array( 'Vis koden ved indgangen' );
		if ( '' !== $order_number ) {
			array_unshift( $notes, $order_number );
		}
		$pad = min( 8.0, $rect['w'] * 0.05, $rect['h'] * 0.04 );
		$inner = max( 20.0, $rect['w'] - ( $pad * 2 ) );
		$label_size = min( 11.0, max( 7.0, $rect['h'] * 0.05 ) );
		$code_size = min( 9.0, max( 6.5, $rect['h'] * 0.04 ) );
		$code_size = $this->fit_lines( $doc, array( $code ), $code_size, $inner, false );
		if ( $doc->text_width( $code, $code_size, false ) > $inner && count( $groups ) > 1 ) {
			$mid = (int) ceil( count( $groups ) / 2 );
			$code_lines = array(
				implode( ' ', array_slice( $groups, 0, $mid ) ),
				implode( ' ', array_slice( $groups, $mid ) ),
			);
			$code_size = $this->fit_lines( $doc, $code_lines, min( 9.0, $rect['h'] * 0.04 ), $inner, false );
		}
		$label_size = $this->fit_lines( $doc, array( $ticket_label ), $label_size, $inner, true );
		$note_size = $this->fit_lines( $doc, $notes, $code_size, $inner, false );
		$leading = max( $code_size, $note_size ) + 2.4;
		$text_h = $label_size + 4 + ( ( count( $code_lines ) + count( $notes ) ) * $leading );
		$limit = $rect['y'] + $rect['h'] - $pad;

		if ( $show_qr ) {
			$doc->fill_rgb( 255, 255, 255 );
			$doc->rect( $rect['x'], $rect['y'], $rect['w'], $rect['h'], 'F' );
			$qr = min( $inner * 0.82, max( 18.0, $rect['h'] - $text_h - ( $pad * 2 ) ) );
			$baseline = $rect['y'] + $pad + $qr + 4 + $label_size;
			while ( $baseline + ( ( count( $code_lines ) + count( $notes ) ) * $leading ) > $limit && $qr > 18 ) {
				$qr -= 4;
				$baseline = $rect['y'] + $pad + $qr + 4 + $label_size;
			}
			$matrix = QrCode::matrix( Token::normalize( $token ) );
			$doc->qr( $rect['x'] + ( ( $rect['w'] - $qr ) / 2 ), $rect['y'] + $pad, $qr, $matrix );
			$doc->fill_rgb( 0, 0, 0 );
		} else {
			$baseline = $rect['y'] + $pad + $label_size;
			$doc->fill_rgb( $ink[0], $ink[1], $ink[2] );
		}

		$this->centered_in( $doc, $rect['x'], $rect['w'], $baseline, $label_size, $ticket_label, true );
		$baseline += $leading;
		foreach ( $code_lines as $line ) {
			$this->centered_in( $doc, $rect['x'], $rect['w'], $baseline, $code_size, $line, false );
			$baseline += $leading;
		}
		foreach ( $notes as $line ) {
			$this->centered_in( $doc, $rect['x'], $rect['w'], $baseline, $note_size, $line, false );
			$baseline += $leading;
		}
	}

	/**
	 * @param list<string> $lines
	 */
	private function fit_lines( Document $doc, array $lines, float $size, float $max_width, bool $bold ): float {
		foreach ( $lines as $line ) {
			$size = $this->fit_size( $doc, $line, $size, $max_width, $bold );
		}

		return $size;
	}

	/**
	 * @param array{data:string,w:int,h:int}         $logo
	 * @param array{x:float,y:float,w:float,h:float} $rect
	 */
	private function place_logo( Document $doc, array $logo, array $rect ): void {
		$px_w = max( 1, (int) $logo['w'] );
		$px_h = max( 1, (int) $logo['h'] );
		$fit = min( $rect['w'] / $px_w, $rect['h'] / $px_h );
		$draw_w = $px_w * $fit;
		$draw_h = $px_h * $fit;
		$doc->jpeg(
			$rect['x'] + ( ( $rect['w'] - $draw_w ) / 2 ),
			$rect['y'] + ( ( $rect['h'] - $draw_h ) / 2 ),
			$draw_w,
			$draw_h,
			$logo['data'],
			$px_w,
			$px_h
		);
	}

	private function fit_size( Document $doc, string $text, float $size, float $max_width, bool $bold ): float {
		while ( $size > 4.5 && $doc->text_width( $text, $size, $bold ) > $max_width ) {
			$size -= 0.25;
		}

		return $size;
	}

	private function centered_in( Document $doc, float $box_x, float $box_w, float $baseline, float $size, string $text, bool $bold ): void {
		$width = $doc->text_width( $text, $size, $bold );
		$x = $box_x + max( 2.0, ( $box_w - $width ) / 2 );
		$doc->text( $x, $baseline, $size, $text, $bold );
	}
}
