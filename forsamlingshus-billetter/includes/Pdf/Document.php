<?php
/**
 * Small PDF writer for tickets and order exports.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Pdf;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a PDF 1.4 document with Helvetica text, rectangles and JPEG images.
 *
 * Public coordinates start at the top left of an A4 page, in points.
 */
final class Document {

	public const PAGE_WIDTH = 595.28;

	public const PAGE_HEIGHT = 841.89;

	/**
	 * Helvetica widths in 1000ths of an em. Unknown glyphs use 600.
	 *
	 * @var array<string, int>
	 */
	private const WIDTHS = array(
		' ' => 278,
		'!' => 278,
		'"' => 355,
		'#' => 556,
		'$' => 556,
		'%' => 889,
		'&' => 667,
		"'" => 191,
		'(' => 333,
		')' => 333,
		'*' => 389,
		'+' => 584,
		',' => 278,
		'-' => 333,
		'.' => 278,
		'/' => 278,
		'0' => 556,
		'1' => 556,
		'2' => 556,
		'3' => 556,
		'4' => 556,
		'5' => 556,
		'6' => 556,
		'7' => 556,
		'8' => 556,
		'9' => 556,
		':' => 278,
		';' => 278,
		'?' => 556,
		'@' => 1015,
		'A' => 667,
		'B' => 667,
		'C' => 722,
		'D' => 722,
		'E' => 667,
		'F' => 611,
		'G' => 778,
		'H' => 722,
		'I' => 278,
		'J' => 500,
		'K' => 667,
		'L' => 556,
		'M' => 833,
		'N' => 722,
		'O' => 778,
		'P' => 667,
		'Q' => 778,
		'R' => 722,
		'S' => 667,
		'T' => 611,
		'U' => 722,
		'V' => 667,
		'W' => 944,
		'X' => 667,
		'Y' => 667,
		'Z' => 611,
		'a' => 556,
		'b' => 556,
		'c' => 500,
		'd' => 556,
		'e' => 556,
		'f' => 278,
		'g' => 556,
		'h' => 556,
		'i' => 222,
		'j' => 222,
		'k' => 500,
		'l' => 222,
		'm' => 833,
		'n' => 556,
		'o' => 556,
		'p' => 556,
		'q' => 556,
		'r' => 333,
		's' => 500,
		't' => 278,
		'u' => 556,
		'v' => 500,
		'w' => 722,
		'x' => 500,
		'y' => 500,
		'z' => 500,
		"\xE6" => 889,
		"\xF8" => 611,
		"\xE5" => 556,
		"\xC6" => 1000,
		"\xD8" => 778,
		"\xC5" => 667,
		"\xE9" => 556,
	);

	/**
	 * @var list<list<string>>
	 */
	private array $pages = array();

	/**
	 * @var list<list<int>>
	 */
	private array $page_images = array();

	/**
	 * @var list<string>
	 */
	private array $ops = array();

	/**
	 * Media box of each closed page, in points.
	 *
	 * @var list<array{0:float,1:float}>
	 */
	private array $page_boxes = array();

	private float $page_width = self::PAGE_WIDTH;

	private float $page_height = self::PAGE_HEIGHT;

	/**
	 * @var list<int>
	 */
	private array $current_images = array();

	private bool $open = false;

	/**
	 * @var list<array{data:string,w:int,h:int}>
	 */
	private array $images = array();

	public function add_page( float $width = 0, float $height = 0 ): void {
		$this->close_page();
		$this->page_width = $width > 0 ? $width : self::PAGE_WIDTH;
		$this->page_height = $height > 0 ? $height : self::PAGE_HEIGHT;
		$this->open = true;
	}

	public function fill_rgb( int $r, int $g, int $b ): void {
		$this->ensure_page();
		$this->ops[] = sprintf( '%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255 );
		$this->ops[] = sprintf( '%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255 );
	}

	/**
	 * @param 'F'|'S'|'B' $style
	 */
	public function rect( float $x, float $y, float $w, float $h, string $style = 'F' ): void {
		$this->ensure_page();
		$pdf_y = $this->page_height - $y - $h;
		$op = match ( $style ) {
			'S' => 'S',
			'B' => 'B',
			default => 'f',
		};
		$this->ops[] = sprintf( '%.2F %.2F %.2F %.2F re %s', $x, $pdf_y, $w, $h, $op );
	}

	/**
	 * $y is the baseline, measured from the top of the page.
	 */
	public function text( float $x, float $y, float $size, string $text, bool $bold = false ): void {
		$this->ensure_page();
		$font = $bold ? 'F2' : 'F1';
		$pdf_y = $this->page_height - $y;
		$encoded = $this->escape( $this->winansi( $text ) );
		$this->ops[] = sprintf( 'BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET', $font, $size, $x, $pdf_y, $encoded );
	}

	public function text_width( string $text, float $size, bool $bold = false ): float {
		$encoded = $this->winansi( $text );
		$units = 0;
		$length = strlen( $encoded );
		for ( $i = 0; $i < $length; $i++ ) {
			$units += self::WIDTHS[ $encoded[ $i ] ] ?? 600;
		}
		$width = ( $units / 1000 ) * $size;

		return $bold ? $width * 1.08 : $width;
	}

	/**
	 * Writes wrapped text and returns the baseline under the last line.
	 */
	public function paragraph( float $x, float $y, float $size, string $text, float $max_width, float $leading, bool $bold = false ): float {
		$words = preg_split( '/\s+/u', trim( $text ) );
		if ( ! is_array( $words ) ) {
			return $y;
		}
		$line = '';
		foreach ( $words as $word ) {
			if ( ! is_string( $word ) || '' === $word ) {
				continue;
			}
			$candidate = '' === $line ? $word : $line . ' ' . $word;
			if ( $this->text_width( $candidate, $size, $bold ) <= $max_width ) {
				$line = $candidate;
				continue;
			}
			if ( '' !== $line ) {
				$this->text( $x, $y, $size, $line, $bold );
				$y += $leading;
			}
			if ( $this->text_width( $word, $size, $bold ) > $max_width ) {
				$y = $this->write_long_word( $x, $y, $size, $word, $max_width, $leading, $bold );
				$line = '';
			} else {
				$line = $word;
			}
		}
		if ( '' !== $line ) {
			$this->text( $x, $y, $size, $line, $bold );
			$y += $leading;
		}

		return $y;
	}

	public function jpeg( float $x, float $y, float $w, float $h, string $binary, int $px_w, int $px_h ): void {
		$this->paint_jpeg( $x, $y, $w, $h, $binary, $px_w, $px_h, false );
	}

	/**
	 * Draws a JPEG and clips it to the page, so a zoomed or shifted photo
	 * cannot paint past the ticket edge. The paper color stays visible outside it.
	 */
	public function clipped_jpeg( float $x, float $y, float $w, float $h, string $binary, int $px_w, int $px_h ): void {
		$this->paint_jpeg( $x, $y, $w, $h, $binary, $px_w, $px_h, true );
	}

	private function paint_jpeg( float $x, float $y, float $w, float $h, string $binary, int $px_w, int $px_h, bool $clip ): void {
		$this->ensure_page();
		$index = count( $this->images );
		$this->images[] = array(
			'data' => $binary,
			'w' => $px_w,
			'h' => $px_h,
		);
		$this->current_images[] = $index;
		$pdf_y = $this->page_height - $y - $h;
		if ( $clip ) {
			$this->ops[] = sprintf(
				'q 0 0 %.2F %.2F re W n %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q',
				$this->page_width,
				$this->page_height,
				$w,
				$h,
				$x,
				$pdf_y,
				$index
			);
			return;
		}
		$this->ops[] = sprintf( 'q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q', $w, $h, $x, $pdf_y, $index );
	}

	/**
	 * Draws a QR matrix on a white square so scanners are not confused by a tinted page.
	 *
	 * @param list<list<bool>> $modules
	 */
	public function qr( float $x, float $y, float $size, array $modules ): void {
		$count = count( $modules );
		if ( $count < 1 ) {
			return;
		}
		$this->fill_rgb( 255, 255, 255 );
		$this->rect( $x, $y, $size, $size, 'F' );
		$this->fill_rgb( 0, 0, 0 );
		$cell = $size / $count;
		for ( $row = 0; $row < $count; $row++ ) {
			$col = 0;
			$line = $modules[ $row ];
			while ( $col < $count ) {
				if ( empty( $line[ $col ] ) ) {
					++$col;
					continue;
				}
				$start = $col;
				while ( $col < $count && ! empty( $line[ $col ] ) ) {
					++$col;
				}
				$this->rect( $x + ( $start * $cell ), $y + ( $row * $cell ), ( $col - $start ) * $cell, $cell, 'F' );
			}
		}
	}

	public function output(): string {
		$this->close_page();
		if ( array() === $this->pages ) {
			$this->add_page();
			$this->close_page();
		}

		$image_count = count( $this->images );
		$page_count = count( $this->pages );
		$content_ids = array();
		$page_ids = array();
		$next = 5 + $image_count;
		for ( $i = 0; $i < $page_count; $i++ ) {
			$content_ids[ $i ] = $next++;
			$page_ids[ $i ] = $next++;
		}

		$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		$obj_num = 0;
		$add = static function ( string $body ) use ( &$pdf, &$offsets, &$obj_num ): void {
			++$obj_num;
			$offsets[ $obj_num ] = strlen( $pdf );
			$pdf .= $obj_num . " 0 obj\n" . $body . "\nendobj\n";
		};

		$kids = implode(
			' ',
			array_map(
				static function ( int $id ): string {
					return $id . ' 0 R';
				},
				$page_ids
			)
		);
		$add( '<< /Type /Catalog /Pages 2 0 R >>' );
		$add( '<< /Type /Pages /Kids [' . $kids . '] /Count ' . $page_count . ' >>' );
		$add( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>' );
		$add( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>' );

		foreach ( $this->images as $image ) {
			$data = $image['data'];
			$add(
				'<< /Type /XObject /Subtype /Image /Width ' . $image['w']
				. ' /Height ' . $image['h']
				. ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $data )
				. " >>\nstream\n" . $data . "\nendstream"
			);
		}

		foreach ( $this->pages as $index => $ops ) {
			$stream = implode( "\n", $ops );
			$add( '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream" );

			$xobject = '';
			if ( ! empty( $this->page_images[ $index ] ) ) {
				$parts = array();
				foreach ( $this->page_images[ $index ] as $image_index ) {
					$parts[] = '/Im' . $image_index . ' ' . ( 5 + $image_index ) . ' 0 R';
				}
				$xobject = ' /XObject << ' . implode( ' ', $parts ) . ' >>';
			}
			$box = $this->page_boxes[ $index ] ?? array( self::PAGE_WIDTH, self::PAGE_HEIGHT );
			$add(
				'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . sprintf( '%.2F', $box[0] ) . ' ' . sprintf( '%.2F', $box[1] ) . ']'
				. ' /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . $xobject . ' >>'
				. ' /Contents ' . $content_ids[ $index ] . ' 0 R >>'
			);
		}

		$startxref = strlen( $pdf );
		$size = $obj_num + 1;
		$pdf .= "xref\n0 {$size}\n";
		$pdf .= "0000000000 65535 f \n";
		for ( $i = 1; $i <= $obj_num; $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}
		$pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\n";
		$pdf .= "startxref\n{$startxref}\n%%EOF";

		return $pdf;
	}

	private function ensure_page(): void {
		if ( ! $this->open ) {
			$this->add_page();
		}
	}

	private function close_page(): void {
		if ( ! $this->open ) {
			return;
		}
		$this->pages[] = $this->ops;
		$this->page_images[] = $this->current_images;
		$this->page_boxes[] = array( $this->page_width, $this->page_height );
		$this->ops = array();
		$this->current_images = array();
		$this->open = false;
	}

	private function write_long_word( float $x, float $y, float $size, string $word, float $max_width, float $leading, bool $bold ): float {
		$chunk = '';
		$chars = preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $chars ) ) {
			return $y;
		}
		foreach ( $chars as $char ) {
			$candidate = $chunk . $char;
			if ( '' !== $chunk && $this->text_width( $candidate, $size, $bold ) > $max_width ) {
				$this->text( $x, $y, $size, $chunk, $bold );
				$y += $leading;
				$chunk = $char;
				continue;
			}
			$chunk = $candidate;
		}
		if ( '' !== $chunk ) {
			$this->text( $x, $y, $size, $chunk, $bold );
			$y += $leading;
		}

		return $y;
	}

	private function winansi( string $text ): string {
		$converted = iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $text );
		if ( false !== $converted ) {
			return $converted;
		}

		$mapped = strtr(
			$text,
			array(
				'æ' => "\xE6",
				'ø' => "\xF8",
				'å' => "\xE5",
				'Æ' => "\xC6",
				'Ø' => "\xD8",
				'Å' => "\xC5",
				'é' => "\xE9",
				'–' => '-',
				'—' => '-',
				'’' => "'",
			)
		);
		$ascii = preg_replace( '/[^\x00-\xFF]/', '?', $mapped );

		return is_string( $ascii ) ? $ascii : '';
	}

	private function escape( string $winansi ): string {
		$out = '';
		$length = strlen( $winansi );
		for ( $i = 0; $i < $length; $i++ ) {
			$char = $winansi[ $i ];
			$ord = ord( $char );
			if ( '\\' === $char || '(' === $char || ')' === $char ) {
				$out .= '\\' . $char;
			} elseif ( $ord < 32 || $ord > 126 ) {
				$out .= sprintf( '\\%03o', $ord );
			} else {
				$out .= $char;
			}
		}

		return $out;
	}
}
