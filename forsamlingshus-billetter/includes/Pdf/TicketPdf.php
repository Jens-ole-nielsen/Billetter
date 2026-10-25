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
 * Block positions and box sizes come from the designer as fractions of the
 * page. The photo uses the same fit, zoom and offset as the preview. Text and
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

		$scene = TicketFrame::scene(
			$photo ? (int) $background['w'] : 0,
			$photo ? (int) $background['h'] : 0,
			$page_w,
			$page_h,
			$context['bg_scale'] ?? 1000,
			$context['bg_x'] ?? 0,
			$context['bg_y'] ?? 0,
			$blocks
		);
		$boxes = $scene['blocks'];

		$doc->fill_rgb( $paper[0], $paper[1], $paper[2] );
		$doc->rect( 0, 0, $page_w, $page_h, 'F' );
		if ( $photo ) {
			$frame = $scene['photo'];
			$doc->clipped_jpeg( $frame['x'], $frame['y'], $frame['w'], $frame['h'], $background['data'], (int) $background['w'], (int) $background['h'] );
		}

		$pads = TicketFrame::pads_content( $context['pad_blocks'] ?? 'auto', $photo );
		$surface = $pads ? $pad_color : $paper;
		$label_ink = ( $photo && ! $pads ) ? $primary : $this->ink_on( $primary, $surface );
		$value_ink = ( $photo && ! $pads ) ? $text : $this->ink_on( $text, $surface );

		if ( is_array( $logo ) && isset( $logo['data'], $logo['w'], $logo['h'] ) && is_string( $logo['data'] ) ) {
			$this->place_logo( $doc, $logo, $boxes['logo'], $pads );
		}

		$this->place_heading(
			$doc,
			(string) ( $context['heading'] ?? 'Adgangsbillet' ),
			$boxes['heading'],
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
			$this->place_field( $doc, $field[1], $field[2], $boxes[ $name ], $scale, $label_ink, $value_ink, $pads );
		}

		$sequence = max( 1, (int) $ticket['sequence'] );
		$this->place_qr(
			$doc,
			(string) $ticket['token'],
			'Billet ' . $sequence . ' af ' . $total,
			trim( (string) ( $context['order_number'] ?? '' ) ),
			$boxes['qr'],
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
	 * @param array{x:float,y:float,w:float,h:float} $rect
	 * @param array{0:int,1:int,2:int}               $ink
	 */
	private function place_heading( Document $doc, string $heading, array $rect, float $scale, array $ink, bool $pads ): void {
		$pad = 8.0;
		$inner = max( 20.0, $rect['w'] - ( $pad * 2 ) );
		$size = $this->fit_size( $doc, $heading, max( 13.0, 16.0 * $scale ), $inner, true );
		$this->pad( $doc, $rect['x'], $rect['y'], $rect['w'], $rect['h'], $pads );
		$doc->fill_rgb( $ink[0], $ink[1], $ink[2] );
		$doc->paragraph( $rect['x'] + $pad, $rect['y'] + $size + 2, $size, $heading, $inner, $size + 3, true );
	}

	/**
	 * @param array{x:float,y:float,w:float,h:float} $rect
	 * @param array{0:int,1:int,2:int}               $label_ink
	 * @param array{0:int,1:int,2:int}               $value_ink
	 */
	private function place_field( Document $doc, string $label, string $value, array $rect, float $scale, array $label_ink, array $value_ink, bool $pads ): void {
		$pad = 8.0;
		$inner = max( 20.0, $rect['w'] - ( $pad * 2 ) );
		$label_size = $this->fit_size( $doc, $label, max( 8.0, 9.0 * $scale ), $inner, true );
		$value_size = $this->fit_size( $doc, $value, max( 11.0, 13.0 * $scale ), $inner, true );
		$this->pad( $doc, $rect['x'], $rect['y'], $rect['w'], $rect['h'], $pads );
		$doc->fill_rgb( $label_ink[0], $label_ink[1], $label_ink[2] );
		$doc->text( $rect['x'] + $pad, $rect['y'] + $label_size + 2, $label_size, $label, true );
		$doc->fill_rgb( $value_ink[0], $value_ink[1], $value_ink[2] );
		$doc->paragraph( $rect['x'] + $pad, $rect['y'] + $label_size + $value_size + 4, $value_size, $value, $inner, $value_size + 2, true );
	}

	/**
	 * @param array{x:float,y:float,w:float,h:float} $rect
	 * @param array{0:int,1:int,2:int}               $ink Text color used when the QR code is off.
	 */
	private function place_qr( Document $doc, string $token, string $ticket_label, string $order_number, array $rect, float $scale, bool $show_qr, array $ink, bool $pads ): void {
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
		$pad = min( 12.0, $rect['w'] * 0.06, $rect['h'] * 0.04 );
		$inner = max( 20.0, $rect['w'] - ( $pad * 2 ) );
		$label_size = max( 8.0, 11.0 * $scale );
		$code_size = max( 7.0, 9.5 * $scale );
		$text_w = $this->qr_text_width( $doc, $ticket_label, $code_lines, $notes, $label_size, $code_size );
		while ( $code_size > 6 && $text_w > $inner ) {
			$code_size -= 0.3;
			$label_size = max( 7.0, $code_size + 1.5 );
			$text_w = $this->qr_text_width( $doc, $ticket_label, $code_lines, $notes, $label_size, $code_size );
		}
		$line_h = $code_size + 3;
		$text_h = $label_size + 6 + ( count( $code_lines ) * $line_h ) + ( count( $notes ) * $line_h );
		$qr = 0.0;
		$baseline = $rect['y'] + 8 + $label_size;
		if ( $show_qr ) {
			$doc->fill_rgb( 255, 255, 255 );
			$doc->rect( $rect['x'], $rect['y'], $rect['w'], $rect['h'], 'F' );
			$qr = min( $inner, max( 16.0, $rect['h'] - $text_h - ( $pad * 2 ) ) );
			$matrix = QrCode::matrix( Token::normalize( $token ) );
			$doc->qr( $rect['x'] + ( ( $rect['w'] - $qr ) / 2 ), $rect['y'] + $pad, $qr, $matrix );
			$baseline = $rect['y'] + $pad + $qr + 6 + $label_size;
			$doc->fill_rgb( 0, 0, 0 );
		} else {
			$this->pad( $doc, $rect['x'], $rect['y'], $rect['w'], $rect['h'], $pads );
			$doc->fill_rgb( $ink[0], $ink[1], $ink[2] );
		}
		$this->centered_in( $doc, $rect['x'], $rect['w'], $baseline, $label_size, $ticket_label, true );
		$baseline += $line_h;
		foreach ( $code_lines as $line ) {
			$this->centered_in( $doc, $rect['x'], $rect['w'], $baseline, $code_size, $line, false );
			$baseline += $line_h;
		}
		foreach ( $notes as $line ) {
			$this->centered_in( $doc, $rect['x'], $rect['w'], $baseline, $code_size, $line, false );
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
	 * @param array{data:string,w:int,h:int}         $logo
	 * @param array{x:float,y:float,w:float,h:float} $rect
	 */
	private function place_logo( Document $doc, array $logo, array $rect, bool $pads ): void {
		$px_w = max( 1, (int) $logo['w'] );
		$px_h = max( 1, (int) $logo['h'] );
		$pad = $pads ? min( 4.0, $rect['w'] * 0.08, $rect['h'] * 0.08 ) : 0.0;
		if ( $pads ) {
			$doc->fill_rgb( 255, 255, 255 );
			$doc->rect( $rect['x'], $rect['y'], $rect['w'], $rect['h'], 'F' );
		}
		$inner_w = max( 8.0, $rect['w'] - ( $pad * 2 ) );
		$inner_h = max( 8.0, $rect['h'] - ( $pad * 2 ) );
		$fit = min( $inner_w / $px_w, $inner_h / $px_h );
		$draw_w = $px_w * $fit;
		$draw_h = $px_h * $fit;
		$doc->jpeg(
			$rect['x'] + $pad + ( ( $inner_w - $draw_w ) / 2 ),
			$rect['y'] + $pad + ( ( $inner_h - $draw_h ) / 2 ),
			$draw_w,
			$draw_h,
			$logo['data'],
			$px_w,
			$px_h
		);
	}

	private function fit_size( Document $doc, string $text, float $size, float $max_width, bool $bold ): float {
		while ( $size > 6.0 && $doc->text_width( $text, $size, $bold ) > $max_width ) {
			$size -= 0.4;
		}

		return $size;
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
}
