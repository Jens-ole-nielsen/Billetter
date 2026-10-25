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
 * Renders the designed ticket. Callers pass already formatted Danish strings.
 *
 * Block positions come from the designer as per mille of the page. Text and
 * the logo can sit on a light pad. The QR code always has a white background,
 * and the manual code on that pad is black. Without the QR code, that text
 * uses the same color as the other fields.
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
		$blocks = $this->blocks( $context );
		$scale = $this->scale( $page_w, $page_h );
		$primary = Color::rgb( (string) ( $context['primary'] ?? '#1F4B3A' ), array( 31, 75, 58 ) );
		$text = Color::rgb( (string) ( $context['text'] ?? '#1C1C1C' ), array( 28, 28, 28 ) );
		$paper = Color::rgb( (string) ( $context['background'] ?? '#FFFBF5' ), array( 255, 251, 245 ) );
		$photo = is_array( $background ) && isset( $background['data'], $background['w'], $background['h'] ) && is_string( $background['data'] );
		$pad_color = array( 255, 255, 255 );

		$doc->fill_rgb( $paper[0], $paper[1], $paper[2] );
		$doc->rect( 0, 0, $page_w, $page_h, 'F' );
		if ( $photo ) {
			$frame = TicketFrame::photo_frame(
				(int) $background['w'],
				(int) $background['h'],
				$page_w,
				$page_h,
				$context['bg_scale'] ?? 1000,
				$context['bg_x'] ?? 0,
				$context['bg_y'] ?? 0
			);
			$doc->clipped_jpeg( $frame['x'], $frame['y'], $frame['w'], $frame['h'], $background['data'], (int) $background['w'], (int) $background['h'] );
		}

		$pads = TicketFrame::pads_content( $context['pad_blocks'] ?? 'auto', $photo );
		$surface = $pads ? $pad_color : $paper;
		$label_ink = ( $photo && ! $pads ) ? $primary : $this->ink_on( $primary, $surface );
		$value_ink = ( $photo && ! $pads ) ? $text : $this->ink_on( $text, $surface );

		if ( is_array( $logo ) && isset( $logo['data'], $logo['w'], $logo['h'] ) && is_string( $logo['data'] ) ) {
			$this->place_logo( $doc, $logo, $page_w, $page_h, $blocks['logo'], $scale, $pads );
		}

		$this->place_heading(
			$doc,
			(string) ( $context['heading'] ?? 'Adgangsbillet' ),
			$page_w,
			$page_h,
			$blocks['heading'],
			$scale,
			$label_ink,
			$pads
		);

		$fields = array(
			'event' => array( 'show_event', 'Arrangement', (string) ( $context['event'] ?? '' ) ),
			'date' => array( 'show_date', 'Dato', (string) ( $context['date'] ?? '' ) ),
			'time' => array( 'show_time', 'Tidspunkt', (string) ( $context['time'] ?? '' ) ),
			'buyer' => array( 'show_buyer', 'Køber', (string) ( $context['buyer'] ?? '' ) ),
			'price' => array( 'show_price', 'Pris', (string) ( $context['price'] ?? '' ) ),
		);
		foreach ( $fields as $name => $field ) {
			if ( empty( $context[ $field[0] ] ) || '' === $field[2] ) {
				continue;
			}
			$this->place_field( $doc, $field[1], $field[2], $page_w, $page_h, $blocks[ $name ], $scale, $label_ink, $value_ink, $pads );
		}

		$sequence = max( 1, (int) $ticket['sequence'] );
		$this->place_qr(
			$doc,
			(string) $ticket['token'],
			'Billet ' . $sequence . ' af ' . $total,
			trim( (string) ( $context['order_number'] ?? '' ) ),
			$page_w,
			$page_h,
			$blocks['qr'],
			$scale,
			! empty( $context['show_qr'] ),
			$value_ink,
			$pads
		);
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, array{x:int,y:int}>
	 */
	private function blocks( array $context ): array {
		$source = $context['blocks'] ?? null;
		if ( ! is_array( $source ) ) {
			return TicketFrame::blocks_from( $context );
		}
		$flat = array();
		foreach ( TicketFrame::BLOCKS as $name ) {
			$pos = $source[ $name ] ?? null;
			if ( ! is_array( $pos ) ) {
				continue;
			}
			$flat[ 'block_' . $name . '_x' ] = $pos['x'] ?? null;
			$flat[ 'block_' . $name . '_y' ] = $pos['y'] ?? null;
		}

		return TicketFrame::blocks_from( $flat );
	}

	private function scale( float $page_w, float $page_h ): float {
		$scale = min( $page_w, $page_h ) / 420;

		return max( 0.72, min( 1.45, $scale ) );
	}

	/**
	 * @param array{0:int,1:int,2:int} $ink
	 * @param array{0:int,1:int,2:int} $surface
	 * @return array{0:int,1:int,2:int}
	 */
	private function ink_on( array $ink, array $surface ): array {
		$ink_luma = ( ( 0.2126 * $ink[0] ) + ( 0.7152 * $ink[1] ) + ( 0.0722 * $ink[2] ) ) / 255;
		$surface_luma = ( ( 0.2126 * $surface[0] ) + ( 0.7152 * $surface[1] ) + ( 0.0722 * $surface[2] ) ) / 255;
		if ( abs( $ink_luma - $surface_luma ) >= 0.45 ) {
			return $ink;
		}

		return $surface_luma > 0.55 ? array( 28, 28, 28 ) : array( 255, 255, 255 );
	}

	/**
	 * @param array{x:int,y:int}       $block
	 * @param array{0:int,1:int,2:int} $ink
	 */
	private function place_heading( Document $doc, string $heading, float $page_w, float $page_h, array $block, float $scale, array $ink, bool $pads ): void {
		$size = max( 13.0, 16.0 * $scale );
		$x = TicketFrame::origin( $page_w, $block['x'] );
		$y = TicketFrame::origin( $page_h, $block['y'] );
		$natural = $doc->text_width( $heading, $size, true ) + 16;
		$span = min( max( 48.0, $natural ), max( 48.0, $page_w - 16 ) );
		$lines = max( 1, $this->line_count( $doc, $heading, $size, max( 32.0, $span - 16 ), true ) );
		$box_h = 10 + ( $lines * ( $size + 3 ) );
		$placed = TicketFrame::place( $x, $y, $span, $box_h, $page_w, $page_h );
		$this->pad( $doc, $placed['x'], $placed['y'], $placed['w'], $placed['h'], $pads );
		$doc->fill_rgb( $ink[0], $ink[1], $ink[2] );
		$doc->paragraph( $placed['x'] + 8, $placed['y'] + $size + 2, $size, $heading, max( 32.0, $placed['w'] - 16 ), $size + 3, true );
	}

	/**
	 * @param array{x:int,y:int}       $block
	 * @param array{0:int,1:int,2:int} $label_ink
	 * @param array{0:int,1:int,2:int} $value_ink
	 */
	private function place_field( Document $doc, string $label, string $value, float $page_w, float $page_h, array $block, float $scale, array $label_ink, array $value_ink, bool $pads ): void {
		$label_size = max( 8.0, 9.0 * $scale );
		$value_size = max( 11.0, 13.0 * $scale );
		$x = TicketFrame::origin( $page_w, $block['x'] );
		$y = TicketFrame::origin( $page_h, $block['y'] );
		$natural = max( $doc->text_width( $label, $label_size, true ), $doc->text_width( $value, $value_size, true ) ) + 16;
		$span = min( max( 64.0, $natural ), max( 64.0, $page_w - 16 ) );
		$lines = max( 1, $this->line_count( $doc, $value, $value_size, max( 40.0, $span - 16 ), true ) );
		$box_h = 8 + $label_size + 2 + ( $lines * ( $value_size + 2 ) ) + 4;
		$placed = TicketFrame::place( $x, $y, $span, $box_h, $page_w, $page_h );
		$this->pad( $doc, $placed['x'], $placed['y'], $placed['w'], $placed['h'], $pads );
		$doc->fill_rgb( $label_ink[0], $label_ink[1], $label_ink[2] );
		$doc->text( $placed['x'] + 8, $placed['y'] + $label_size + 2, $label_size, $label, true );
		$doc->fill_rgb( $value_ink[0], $value_ink[1], $value_ink[2] );
		$doc->paragraph( $placed['x'] + 8, $placed['y'] + $label_size + $value_size + 4, $value_size, $value, max( 40.0, $placed['w'] - 16 ), $value_size + 2, true );
	}

	/**
	 * @param array{x:int,y:int}       $block
	 * @param array{0:int,1:int,2:int} $ink Text color used when the QR code is off.
	 */
	private function place_qr( Document $doc, string $token, string $ticket_label, string $order_number, float $page_w, float $page_h, array $block, float $scale, bool $show_qr, array $ink, bool $pads ): void {
		$x = TicketFrame::origin( $page_w, $block['x'] );
		$y = TicketFrame::origin( $page_h, $block['y'] );
		$groups = preg_split( '/\s+/', trim( Token::grouped( $token ) ) );
		$groups = is_array( $groups ) ? $groups : array();
		$code_lines = array();
		if ( count( $groups ) > 4 ) {
			$code_lines[] = implode( ' ', array_slice( $groups, 0, 4 ) );
			$code_lines[] = implode( ' ', array_slice( $groups, 4 ) );
		} else {
			$code_lines[] = implode( ' ', $groups );
		}
		$notes = array( 'Vis koden ved indgangen' );
		if ( '' !== $order_number ) {
			array_unshift( $notes, $order_number );
		}
		$label_size = max( 9.0, 11.0 * $scale );
		$code_size = max( 8.0, 9.5 * $scale );
		$pad = 14.0;
		$limit = max( 96.0, $page_w - $x - 8 );
		$text_w = $this->qr_text_width( $doc, $ticket_label, $code_lines, $notes, $label_size, $code_size );
		while ( $code_size > 7 && $text_w > $limit ) {
			$code_size -= 0.4;
			$label_size = max( 8.0, $code_size + 1.5 );
			$text_w = $this->qr_text_width( $doc, $ticket_label, $code_lines, $notes, $label_size, $code_size );
		}
		$qr = $show_qr ? min( 210.0, max( 96.0, min( $page_w, $page_h ) * 0.32 ), $limit - ( $pad * 2 ) ) : 0.0;
		$box_w = min( $limit, max( $qr + ( $pad * 2 ), $text_w + 24 ) );
		$line_h = $code_size + 3;
		$text_h = $label_size + 6 + ( count( $code_lines ) * $line_h ) + ( count( $notes ) * $line_h );
		$box_h = ( $show_qr ? $pad + $qr + 8 : 10 ) + $text_h + 8;
		$placed = TicketFrame::place( $x, $y, $box_w, $box_h, $page_w, $page_h );
		$fit = min( 1.0, $box_w > 0 ? $placed['w'] / $box_w : 1.0, $box_h > 0 ? $placed['h'] / $box_h : 1.0 );
		$qr *= $fit;
		$pad *= $fit;
		$label_size *= $fit;
		$code_size *= $fit;
		$line_h *= $fit;
		$baseline = $placed['y'] + 10 + $label_size;
		if ( $show_qr ) {
			$doc->fill_rgb( 255, 255, 255 );
			$doc->rect( $placed['x'], $placed['y'], $placed['w'], $placed['h'], 'F' );
			$matrix = QrCode::matrix( Token::normalize( $token ) );
			$doc->qr( $placed['x'] + ( ( $placed['w'] - $qr ) / 2 ), $placed['y'] + $pad, $qr, $matrix );
			$baseline = $placed['y'] + $pad + $qr + 8 + $label_size;
			$doc->fill_rgb( 0, 0, 0 );
		} else {
			$this->pad( $doc, $placed['x'], $placed['y'], $placed['w'], $placed['h'], $pads );
			$doc->fill_rgb( $ink[0], $ink[1], $ink[2] );
		}
		$this->centered_in( $doc, $placed['x'], $placed['w'], $baseline, $label_size, $ticket_label, true );
		$baseline += $line_h;
		foreach ( $code_lines as $line ) {
			$this->centered_in( $doc, $placed['x'], $placed['w'], $baseline, $code_size, $line, false );
			$baseline += $line_h;
		}
		foreach ( $notes as $line ) {
			$this->centered_in( $doc, $placed['x'], $placed['w'], $baseline, $code_size, $line, false );
			$baseline += $line_h;
		}
	}

	/**
	 * @param list<string> $code_lines
	 * @param list<string> $notes
	 */
	private function qr_text_width( Document $doc, string $label, array $code_lines, array $notes, float $label_size, float $code_size ): float {
		$width = $doc->text_width( $label, $label_size, true );
		foreach ( array_merge( $code_lines, $notes ) as $line ) {
			$width = max( $width, $doc->text_width( $line, $code_size, false ) );
		}

		return $width;
	}

	/**
	 * @param array{data:string,w:int,h:int} $logo
	 * @param array{x:int,y:int}              $block
	 */
	private function place_logo( Document $doc, array $logo, float $page_w, float $page_h, array $block, float $scale, bool $pads ): void {
		$px_w = max( 1, (int) $logo['w'] );
		$px_h = max( 1, (int) $logo['h'] );
		$max_w = min( 120.0, $page_w * 0.28 ) * min( 1.0, $scale );
		$max_h = min( 78.0, $page_h * 0.16 ) * min( 1.0, $scale );
		$max_w = max( 48.0, $max_w );
		$max_h = max( 32.0, $max_h );
		$fit = min( $max_w / $px_w, $max_h / $px_h );
		$w = $px_w * $fit;
		$h = $px_h * $fit;
		$x = TicketFrame::origin( $page_w, $block['x'] );
		$y = TicketFrame::origin( $page_h, $block['y'] );
		$pad = $pads ? 4.0 : 0.0;
		$placed = TicketFrame::place( $x, $y, $w + ( $pad * 2 ), $h + ( $pad * 2 ), $page_w, $page_h );
		$draw_w = max( 8.0, $placed['w'] - ( $pad * 2 ) );
		$draw_h = max( 8.0, $placed['h'] - ( $pad * 2 ) );
		if ( $pads ) {
			$doc->fill_rgb( 255, 255, 255 );
			$doc->rect( $placed['x'], $placed['y'], $placed['w'], $placed['h'], 'F' );
		}
		$doc->jpeg( $placed['x'] + $pad, $placed['y'] + $pad, $draw_w, $draw_h, $logo['data'], $px_w, $px_h );
	}

	private function pad( Document $doc, float $x, float $y, float $w, float $h, bool $on ): void {
		if ( ! $on ) {
			return;
		}
		$doc->fill_rgb( 255, 255, 255 );
		$doc->rect( $x, $y, $w, $h, 'F' );
	}

	private function centered_in( Document $doc, float $box_x, float $box_w, float $baseline, float $size, string $text, bool $bold ): void {
		$width = $doc->text_width( $text, $size, $bold );
		$x = $box_x + max( 4, ( $box_w - $width ) / 2 );
		$doc->text( $x, $baseline, $size, $text, $bold );
	}

	private function line_count( Document $doc, string $text, float $size, float $max_width, bool $bold ): int {
		$words = preg_split( '/\s+/u', trim( $text ) );
		if ( ! is_array( $words ) ) {
			return 1;
		}
		$lines = 1;
		$line = '';
		foreach ( $words as $word ) {
			if ( ! is_string( $word ) || '' === $word ) {
				continue;
			}
			$candidate = '' === $line ? $word : $line . ' ' . $word;
			if ( $doc->text_width( $candidate, $size, $bold ) <= $max_width ) {
				$line = $candidate;
				continue;
			}
			if ( '' !== $line ) {
				++$lines;
			}
			$line = $word;
		}

		return max( 1, $lines );
	}
}
