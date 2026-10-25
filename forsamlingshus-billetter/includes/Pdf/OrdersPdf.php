<?php
/**
 * PDF list of orders for the treasurer.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Pdf;

use Forsamlingshus\Billetter\Money;
use Forsamlingshus\Billetter\OrderExport;

defined( 'ABSPATH' ) || exit;

/**
 * Dense A4 table: one order per row, with the payable total at the bottom.
 */
final class OrdersPdf {

	private const SIZE = 7.5;

	private const LEADING = 9.0;

	private const MARGIN = 28.0;

	/**
	 * Widths sum to 539, which leaves the same margin on the right of A4.
	 *
	 * @var list<array{key:string,label:string,width:float}>
	 */
	private const COLUMNS = array(
		array(
			'key' => 'number',
			'label' => 'Ordrenummer',
			'width' => 58.0,
		),
		array(
			'key' => 'event',
			'label' => 'Arrangement',
			'width' => 54.0,
		),
		array(
			'key' => 'buyer',
			'label' => 'Køber',
			'width' => 58.0,
		),
		array(
			'key' => 'email',
			'label' => 'E-mail',
			'width' => 84.0,
		),
		array(
			'key' => 'phone',
			'label' => 'Telefon',
			'width' => 48.0,
		),
		array(
			'key' => 'quantity',
			'label' => 'Antal',
			'width' => 28.0,
		),
		array(
			'key' => 'amount',
			'label' => 'Beløb',
			'width' => 50.0,
		),
		array(
			'key' => 'payment',
			'label' => 'Betaling',
			'width' => 54.0,
		),
		array(
			'key' => 'remark',
			'label' => 'Bemærkning',
			'width' => 105.0,
		),
	);

	/**
	 * @param list<array{number:string,event:string,buyer:string,email:string,phone:string,quantity:int,amount:string,amount_ore:int,discount:string,payment:string,remark:string}> $rows
	 */
	public function render( array $rows, string $title, string $subtitle ): string {
		$doc = new Document();
		$doc->add_page();
		$y = $this->header( $doc, $title, $subtitle );
		$tickets = 0;
		$ore = 0;

		if ( array() === $rows ) {
			$doc->fill_rgb( 28, 28, 28 );
			$doc->text( self::MARGIN, $y + 12, 10, 'Ingen ordrer.', false );
			$y += 20;
		}

		foreach ( $rows as $index => $row ) {
			$cells = $this->cells( $doc, $row );
			$line_count = 1;
			foreach ( $cells as $lines ) {
				$line_count = max( $line_count, count( $lines ) );
			}
			$height = ( $line_count * self::LEADING ) + 5;
			if ( $y + $height > 800 ) {
				$doc->add_page();
				$y = $this->header( $doc, $title, $subtitle . ' (fortsat)' );
			}
			if ( 1 === $index % 2 ) {
				$doc->fill_rgb( 244, 241, 234 );
				$doc->rect( self::MARGIN, $y, 539, $height, 'F' );
			}
			$doc->fill_rgb( 28, 28, 28 );
			$x = self::MARGIN;
			foreach ( self::COLUMNS as $column_index => $column ) {
				$baseline = $y + 9;
				foreach ( $cells[ $column_index ] as $line_index => $line ) {
					if ( '' === $line ) {
						continue;
					}
					$doc->text( $x + 2, $baseline + ( $line_index * self::LEADING ), self::SIZE, $line, false );
				}
				$x += $column['width'];
			}
			$y += $height;
			$doc->fill_rgb( 220, 216, 208 );
			$doc->rect( self::MARGIN, $y, 539, 0.4, 'F' );
			$tickets += (int) $row['quantity'];
			$ore += (int) $row['amount_ore'];
		}

		if ( $y > 800 ) {
			$doc->add_page();
			$y = 36;
		}
		$doc->fill_rgb( 31, 75, 58 );
		$doc->text( self::MARGIN, $y + 16, 10, 'Billetter i alt: ' . $tickets, true );
		$doc->text( 220, $y + 16, 10, 'Beløb i alt: ' . Money::format_ore( $ore ), true );

		return $doc->output();
	}

	private function header( Document $doc, string $title, string $subtitle ): float {
		$doc->fill_rgb( 31, 75, 58 );
		$doc->text( self::MARGIN, 26, 14, $title, true );
		$doc->fill_rgb( 70, 70, 70 );
		$doc->text( self::MARGIN, 40, 8, $subtitle, false );

		$heads = array();
		$line_count = 1;
		foreach ( self::COLUMNS as $column ) {
			$wrapped = $this->wrap( $doc, $column['label'], $column['width'] - 4, true );
			$heads[] = $wrapped;
			$line_count = max( $line_count, count( $wrapped ) );
		}

		$top = 52.0;
		$doc->fill_rgb( 31, 75, 58 );
		$x = self::MARGIN;
		foreach ( self::COLUMNS as $index => $column ) {
			foreach ( $heads[ $index ] as $line_index => $line ) {
				$doc->text( $x + 2, $top + 8 + ( $line_index * self::LEADING ), self::SIZE, $line, true );
			}
			$x += $column['width'];
		}
		$rule = $top + ( $line_count * self::LEADING ) + 4;
		$doc->rect( self::MARGIN, $rule, 539, 1.1, 'F' );

		return $rule + 1;
	}

	/**
	 * @param array{number:string,event:string,buyer:string,email:string,phone:string,quantity:int,amount:string,discount:string,payment:string,remark:string} $row
	 * @return list<list<string>>
	 */
	private function cells( Document $doc, array $row ): array {
		$cells = array();
		foreach ( self::COLUMNS as $column ) {
			$key = $column['key'];
			$width = $column['width'] - 4;
			if ( 'quantity' === $key ) {
				$cells[] = array( (string) (int) $row['quantity'] );
				continue;
			}
			$text = (string) $row[ $key ];
			if ( 'remark' === $key ) {
				$text = OrderExport::csv_remark( $text, (string) $row['discount'] );
			}
			$cells[] = $this->wrap( $doc, $text, $width, false );
		}

		return $cells;
	}

	/**
	 * @return list<string>
	 */
	private function wrap( Document $doc, string $text, float $width, bool $bold ): array {
		$collapsed = preg_replace( '/\s+/u', ' ', trim( $text ) );
		$text = is_string( $collapsed ) ? $collapsed : '';
		if ( '' === $text ) {
			return array( '' );
		}
		$words = preg_split( '/ /u', $text );
		if ( ! is_array( $words ) ) {
			return array( $text );
		}
		$lines = array();
		$line = '';
		foreach ( $words as $word ) {
			if ( ! is_string( $word ) || '' === $word ) {
				continue;
			}
			$candidate = '' === $line ? $word : $line . ' ' . $word;
			if ( $doc->text_width( $candidate, self::SIZE, $bold ) <= $width ) {
				$line = $candidate;
				continue;
			}
			if ( '' !== $line ) {
				$lines[] = $line;
				$line = '';
			}
			if ( $doc->text_width( $word, self::SIZE, $bold ) <= $width ) {
				$line = $word;
				continue;
			}
			$pieces = $this->split_word( $doc, $word, $width, $bold );
			$last = array_pop( $pieces );
			foreach ( $pieces as $piece ) {
				$lines[] = $piece;
			}
			$line = is_string( $last ) ? $last : '';
		}
		if ( '' !== $line ) {
			$lines[] = $line;
		}

		return array() === $lines ? array( '' ) : $lines;
	}

	/**
	 * @return list<string>
	 */
	private function split_word( Document $doc, string $word, float $width, bool $bold ): array {
		$pieces = array();
		$chunk = '';
		$chars = preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $chars ) ) {
			return array( $word );
		}
		foreach ( $chars as $char ) {
			if ( ! is_string( $char ) ) {
				continue;
			}
			$candidate = $chunk . $char;
			if ( '' !== $chunk && $doc->text_width( $candidate, self::SIZE, $bold ) > $width ) {
				$pieces[] = $chunk;
				$chunk = $char;
				continue;
			}
			$chunk = $candidate;
		}
		if ( '' !== $chunk ) {
			$pieces[] = $chunk;
		}

		return $pieces;
	}
}
