<?php
/**
 * The printed ticket page, and how a photo is placed on it.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Ticket sizes at 150 pixels per inch, and where each block sits on the page.
 *
 * Positions are per mille of the page, measured from the top left, so they
 * follow the chosen size and match the designer preview.
 */
final class TicketFrame {

	public const DPI = 150;

	/**
	 * @var list<string>
	 */
	public const BLOCKS = array( 'heading', 'event', 'date', 'time', 'buyer', 'price', 'qr', 'logo' );

	/**
	 * @return array<string, array{label:string,orientation:string,w:float,h:float}>
	 */
	public static function sizes(): array {
		return array(
			'a4' => array(
				'label' => 'A4 stående',
				'orientation' => 'stående',
				'w' => 595.28,
				'h' => 841.89,
			),
			'a5' => array(
				'label' => 'A5 stående',
				'orientation' => 'stående',
				'w' => 419.53,
				'h' => 595.28,
			),
			'a6' => array(
				'label' => 'A6 stående',
				'orientation' => 'stående',
				'w' => 297.64,
				'h' => 419.53,
			),
			'a5l' => array(
				'label' => 'A5 liggende',
				'orientation' => 'liggende',
				'w' => 595.28,
				'h' => 419.53,
			),
		);
	}

	/**
	 * @return array{key:string,label:string,orientation:string,w:float,h:float}
	 */
	public static function size( string $key ): array {
		$sizes = self::sizes();
		$key = isset( $sizes[ $key ] ) ? $key : 'a4';
		$size = $sizes[ $key ];
		$size['key'] = $key;

		return $size;
	}

	public static function width_px(): int {
		return self::pixels( 'a4' )['w'];
	}

	public static function height_px(): int {
		return self::pixels( 'a4' )['h'];
	}

	/**
	 * @return array{w:int,h:int}
	 */
	public static function pixels( string $key = 'a4' ): array {
		$size = self::size( $key );

		return array(
			'w' => (int) round( $size['w'] / 72 * self::DPI ),
			'h' => (int) round( $size['h'] / 72 * self::DPI ),
		);
	}

	public static function fit_label( string $key = 'a4' ): string {
		$size = self::size( $key );
		$px = self::pixels( $size['key'] );

		return $px['w'] . ' × ' . $px['h'] . ' pixels, ' . $size['orientation'];
	}

	/**
	 * A chosen ticket image wins. Otherwise the event poster replaces the
	 * design-screen image. The design image is the last fallback.
	 */
	public static function image_id( int $ticket_image_id, int $poster_id, int $design_background_id = 0 ): int {
		if ( $ticket_image_id > 0 ) {
			return $ticket_image_id;
		}
		if ( $poster_id > 0 ) {
			return $poster_id;
		}

		return max( 0, $design_background_id );
	}

	/**
	 * Center crop that covers the ticket. Extra width or height is cut equally from both sides.
	 *
	 * @return array{x:int,y:int,w:int,h:int}
	 */
	public static function cover_crop( int $src_w, int $src_h, int $dst_w, int $dst_h ): array {
		if ( $src_w < 1 || $src_h < 1 || $dst_w < 1 || $dst_h < 1 ) {
			return array(
				'x' => 0,
				'y' => 0,
				'w' => max( 1, $src_w ),
				'h' => max( 1, $src_h ),
			);
		}

		$scale = max( $dst_w / $src_w, $dst_h / $src_h );
		$crop_w = min( $src_w, max( 1, (int) round( $dst_w / $scale ) ) );
		$crop_h = min( $src_h, max( 1, (int) round( $dst_h / $scale ) ) );

		return array(
			'x' => intdiv( $src_w - $crop_w, 2 ),
			'y' => intdiv( $src_h - $crop_h, 2 ),
			'w' => $crop_w,
			'h' => $crop_h,
		);
	}

	/**
	 * 1000 fits the whole photo on the page. Smaller shows more paper. Larger zooms in.
	 */
	public static function photo_scale( mixed $value ): int {
		if ( ! is_numeric( $value ) ) {
			return 1000;
		}
		$number = (int) $value;
		if ( $number < 1 ) {
			return 1000;
		}
		if ( $number < 250 ) {
			return 250;
		}
		if ( $number > 4000 ) {
			return 4000;
		}

		return $number;
	}

	/**
	 * How far the photo's center sits from the page center, per mille of the page.
	 */
	public static function photo_offset( mixed $value ): int {
		if ( ! is_numeric( $value ) ) {
			return 0;
		}
		$number = (int) $value;
		if ( $number < -2000 ) {
			return -2000;
		}
		if ( $number > 2000 ) {
			return 2000;
		}

		return $number;
	}

	/**
	 * Where the photo is drawn. Scale 1000 fits the whole photo: centered
	 * sideways, flush with the top, so a wide poster keeps its full width and
	 * the paper shows underneath. Larger zooms in from that fit. Offsets move
	 * only the photo, per mille of the page. The designer uses the same sum.
	 *
	 * @return array{x:float,y:float,w:float,h:float}
	 */
	public static function photo_frame( int $src_w, int $src_h, float $page_w, float $page_h, mixed $scale = 1000, mixed $offset_x = 0, mixed $offset_y = 0 ): array {
		$scale = self::photo_scale( $scale );
		$offset_x = self::photo_offset( $offset_x );
		$offset_y = self::photo_offset( $offset_y );
		if ( $src_w < 1 || $src_h < 1 || $page_w <= 0.0 || $page_h <= 0.0 ) {
			return array(
				'x' => 0.0,
				'y' => 0.0,
				'w' => max( 0.0, $page_w ),
				'h' => max( 0.0, $page_h ),
			);
		}
		$fit = min( $page_w / $src_w, $page_h / $src_h );
		$draw_w = $src_w * $fit * ( $scale / 1000 );
		$draw_h = $src_h * $fit * ( $scale / 1000 );

		return array(
			'x' => ( ( $page_w - $draw_w ) / 2 ) + ( $page_w * $offset_x / 1000 ),
			'y' => $page_h * $offset_y / 1000,
			'w' => $draw_w,
			'h' => $draw_h,
		);
	}

	/**
	 * Box size as per mille of the page. The designer preview uses the same fractions.
	 *
	 * @return array{w:int,h:int}
	 */
	public static function block_span( string $name ): array {
		return match ( $name ) {
			'heading' => array(
				'w' => 480,
				'h' => 55,
			),
			'qr' => array(
				'w' => 420,
				'h' => 340,
			),
			'logo' => array(
				'w' => 220,
				'h' => 140,
			),
			default => array(
				'w' => 460,
				'h' => 90,
			),
		};
	}

	/**
	 * Photo and element boxes in page units. The PDF draws these rectangles.
	 *
	 * @param array<string, array{x:int,y:int}> $blocks
	 * @return array{photo:array{x:float,y:float,w:float,h:float},blocks:array<string,array{x:float,y:float,w:float,h:float}>}
	 */
	public static function scene( int $src_w, int $src_h, float $page_w, float $page_h, mixed $scale = 1000, mixed $offset_x = 0, mixed $offset_y = 0, array $blocks = array() ): array {
		$photo = self::photo_frame( $src_w, $src_h, $page_w, $page_h, $scale, $offset_x, $offset_y );
		$defaults = self::default_blocks();
		$placed = array();
		foreach ( self::BLOCKS as $name ) {
			$pos = $blocks[ $name ] ?? $defaults[ $name ];
			$span = self::block_span( $name );
			$placed[ $name ] = self::place(
				self::origin( $page_w, (int) ( $pos['x'] ?? $defaults[ $name ]['x'] ) ),
				self::origin( $page_h, (int) ( $pos['y'] ?? $defaults[ $name ]['y'] ) ),
				$page_w * $span['w'] / 1000,
				$page_h * $span['h'] / 1000,
				$page_w,
				$page_h
			);
		}

		return array(
			'photo' => $photo,
			'blocks' => $placed,
		);
	}

	/**
	 * Fractions for the fixed ticket sheet. The preview reads the same numbers.
	 *
	 * photoMax, gap, marginBottom, logoH and logoGap are per mille of the page
	 * height. marginX, columnGap and qrW are per mille of the page width.
	 *
	 * @return array{photoMax:int,gap:int,marginX:int,marginBottom:int,columnGap:int,qrW:int,logoH:int,logoGap:int}
	 */
	public static function sheet_spec(): array {
		return array(
			'photoMax' => 420,
			'gap' => 22,
			'marginX' => 48,
			'marginBottom' => 36,
			'columnGap' => 28,
			'qrW' => 400,
			'logoH' => 100,
			'logoGap' => 12,
		);
	}

	/**
	 * The whole poster sits in the top band. Text and the QR card sit on the
	 * paper under it. A saved drag or zoom is not used: the example and the
	 * PDF both draw this sheet, so the picture cannot depend on which renderer
	 * crops the file.
	 *
	 * @param list<string> $fields Shown blocks, in order. Heading first.
	 * @return array{photo:array{x:float,y:float,w:float,h:float},blocks:array<string,array{x:float,y:float,w:float,h:float}>}
	 */
	public static function sheet( int $src_w, int $src_h, float $page_w, float $page_h, array $fields, bool $logo ): array {
		$spec = self::sheet_spec();
		$max_h = $page_h * $spec['photoMax'] / 1000;
		if ( $src_w < 1 || $src_h < 1 || $page_w <= 0.0 || $page_h <= 0.0 ) {
			$photo = array(
				'x' => 0.0,
				'y' => 0.0,
				'w' => 0.0,
				'h' => 0.0,
			);
			$content_top = $page_h * $spec['gap'] / 1000;
		} else {
			$fit = min( $page_w / $src_w, $max_h / $src_h );
			$draw_w = $src_w * $fit;
			$draw_h = $src_h * $fit;
			$photo = array(
				'x' => ( $page_w - $draw_w ) / 2,
				'y' => 0.0,
				'w' => $draw_w,
				'h' => $draw_h,
			);
			$content_top = $draw_h + ( $page_h * $spec['gap'] / 1000 );
		}

		$margin_x = $page_w * $spec['marginX'] / 1000;
		$bottom = $page_h - ( $page_h * $spec['marginBottom'] / 1000 );
		$qr_w = $page_w * $spec['qrW'] / 1000;
		$gap_x = $page_w * $spec['columnGap'] / 1000;
		$qr_x = $page_w - $margin_x - $qr_w;
		$fields_w = max( 36.0, $qr_x - $gap_x - $margin_x );
		$logo_h = 0.0;
		if ( $logo ) {
			$logo_h = min( $page_h * $spec['logoH'] / 1000, ( $bottom - $content_top ) * 0.22 );
		}
		$blocks = array();
		if ( $logo_h > 0.0 ) {
			$blocks['logo'] = array(
				'x' => $qr_x,
				'y' => $content_top,
				'w' => $qr_w,
				'h' => $logo_h,
			);
		}
		$qr_y = $content_top + ( $logo_h > 0.0 ? $logo_h + ( $page_h * $spec['logoGap'] / 1000 ) : 0.0 );
		$blocks['qr'] = array(
			'x' => $qr_x,
			'y' => $qr_y,
			'w' => $qr_w,
			'h' => max( 36.0, $bottom - $qr_y ),
		);

		$names = array();
		foreach ( $fields as $name ) {
			if ( is_string( $name ) && '' !== $name ) {
				$names[] = $name;
			}
		}
		if ( array() === $names ) {
			$names[] = 'heading';
		}
		$slot = ( $bottom - $content_top ) / count( $names );
		$y = $content_top;
		foreach ( $names as $name ) {
			$blocks[ $name ] = array(
				'x' => $margin_x,
				'y' => $y,
				'w' => $fields_w,
				'h' => $slot,
			);
			$y += $slot;
		}

		return array(
			'photo' => $photo,
			'blocks' => $blocks,
		);
	}

	/**
	 * Default block positions, per mille from the top left.
	 *
	 * @return array<string, array{x:int,y:int}>
	 */
	public static function default_blocks(): array {
		return array(
			'heading' => array(
				'x' => 48,
				'y' => 36,
			),
			'event' => array(
				'x' => 48,
				'y' => 130,
			),
			'date' => array(
				'x' => 48,
				'y' => 270,
			),
			'time' => array(
				'x' => 48,
				'y' => 410,
			),
			'buyer' => array(
				'x' => 48,
				'y' => 550,
			),
			'price' => array(
				'x' => 48,
				'y' => 690,
			),
			'qr' => array(
				'x' => 530,
				'y' => 250,
			),
			'logo' => array(
				'x' => 730,
				'y' => 36,
			),
		);
	}

	/**
	 * @param array<string, mixed> $stored Flat keys block_{name}_x and block_{name}_y, or the design option.
	 * @return array<string, array{x:int,y:int}>
	 */
	public static function blocks_from( array $stored ): array {
		$blocks = self::default_blocks();
		foreach ( $blocks as $name => $pos ) {
			$blocks[ $name ] = array(
				'x' => self::mille( $stored[ 'block_' . $name . '_x' ] ?? $pos['x'] ),
				'y' => self::mille( $stored[ 'block_' . $name . '_y' ] ?? $pos['y'] ),
			);
		}

		return $blocks;
	}

	public static function mille( mixed $value ): int {
		$number = (int) $value;
		if ( $number < 0 ) {
			return 0;
		}
		if ( $number > 1000 ) {
			return 1000;
		}

		return $number;
	}

	/**
	 * Top-left of a block, in points, before the drawn box is pulled back onto the page.
	 */
	public static function origin( float $page, int $mille ): float {
		return $page * self::mille( $mille ) / 1000;
	}

	/**
	 * auto follows the photo. 1 is always. 0 is never. Anything else is auto,
	 * which is what tickets saved before the choice was added still do.
	 */
	public static function pad_choice( mixed $value ): string {
		$choice = is_scalar( $value ) ? (string) $value : 'auto';

		return in_array( $choice, array( 'auto', '1', '0' ), true ) ? $choice : 'auto';
	}

	public static function pads_content( mixed $choice, bool $photo ): bool {
		$choice = self::pad_choice( $choice );
		if ( '1' === $choice ) {
			return true;
		}
		if ( '0' === $choice ) {
			return false;
		}

		return $photo;
	}

	/**
	 * The saved top-left stays. The box shrinks into the room that is left
	 * on the page, and moves only to keep a minimum box on the page.
	 *
	 * @return array{x:float,y:float,w:float,h:float}
	 */
	public static function place( float $x, float $y, float $w, float $h, float $page_w, float $page_h ): array {
		$margin = 8.0;
		$min_w = 36.0;
		$min_h = 16.0;
		$x = max( 0.0, $x );
		$y = max( 0.0, $y );
		$room_w = $page_w - $margin - $x;
		$room_h = $page_h - $margin - $y;
		if ( $room_w < $min_w ) {
			$x = max( $margin, $page_w - $margin - $min_w );
			$room_w = $page_w - $margin - $x;
		}
		if ( $room_h < $min_h ) {
			$y = max( $margin, $page_h - $margin - $min_h );
			$room_h = $page_h - $margin - $y;
		}

		return array(
			'x' => $x,
			'y' => $y,
			'w' => min( max( $min_w, $w ), max( $min_w, $room_w ) ),
			'h' => min( max( $min_h, $h ), max( $min_h, $room_h ) ),
		);
	}
}
