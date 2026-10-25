<?php
/**
 * The printed ticket page, and how a photo is cropped to fill it.
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
}
