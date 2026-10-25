<?php
/**
 * PDF list of orders for the treasurer.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Pdf;

use Forsamlingshus\Billetter\Color;

defined( 'ABSPATH' ) || exit;

/**
 * One row per order: buyer, ticket count and seating remark.
 */
final class OrdersPdf {

	/**
	 * @param list<array{number:string,buyer:string,quantity:int,remark:string,amount:string}> $rows
	 */
	public function render( array $rows, string $title, string $subtitle ): string {
		$doc = new Document();
		$doc->add_page();
		$y = $this->header( $doc, $title, $subtitle );
		$total = 0;

		if ( array() === $rows ) {
			$doc->fill_rgb( 28, 28, 28 );
			$doc->text( 40, $y + 12, 12, 'Ingen ordrer.', false );
		}

		foreach ( $rows as $row ) {
			$remark = '' !== trim( $row['remark'] ) ? $row['remark'] : '—';
			$block_height = 36 + ( 12 * max( 0, substr_count( wordwrap( $remark, 48, "\n", true ), "\n" ) ) );
			if ( $y + $block_height > 790 ) {
				$doc->add_page();
				$y = $this->header( $doc, $title, $subtitle . ' (fortsat)' );
			}
			$doc->fill_rgb( 28, 28, 28 );
			$doc->text( 40, $y, 11, $row['number'], true );
			$doc->text( 130, $y, 11, $row['buyer'], false );
			$doc->text( 320, $y, 11, (string) $row['quantity'], false );
			$doc->text( 370, $y, 11, $row['amount'], false );
			$next = $doc->paragraph( 40, $y + 16, 10, 'Bemærkning: ' . $remark, 515, 13, false );
			$y = $next + 8;
			$doc->fill_rgb( 220, 216, 208 );
			$doc->rect( 40, $y - 6, 515, 0.6, 'F' );
			$total += (int) $row['quantity'];
		}

		if ( $y > 790 ) {
			$doc->add_page();
			$y = 56;
		}
		$doc->fill_rgb( 31, 75, 58 );
		$doc->text( 40, min( $y + 18, 820 ), 12, 'Billetter i alt: ' . $total, true );

		return $doc->output();
	}

	private function header( Document $doc, string $title, string $subtitle ): float {
		$primary = Color::rgb( '#1F4B3A' );
		$doc->fill_rgb( $primary[0], $primary[1], $primary[2] );
		$doc->rect( 0, 0, Document::PAGE_WIDTH, 72, 'F' );
		$doc->fill_rgb( 255, 255, 255 );
		$doc->text( 40, 32, 18, $title, true );
		$doc->text( 40, 52, 11, $subtitle, false );

		$doc->fill_rgb( 31, 75, 58 );
		$doc->text( 40, 100, 10, 'Ordre', true );
		$doc->text( 130, 100, 10, 'Køber', true );
		$doc->text( 320, 100, 10, 'Antal', true );
		$doc->text( 370, 100, 10, 'Beløb', true );
		$doc->fill_rgb( 31, 75, 58 );
		$doc->rect( 40, 108, 515, 1.2, 'F' );

		return 128.0;
	}
}
