<?php
/**
 * The printed ticket page, and how a photo is cropped to fill it.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

use Forsamlingshus\Billetter\Pdf\Document;

defined( 'ABSPATH' ) || exit;

/**
 * A4 portrait at 150 pixels per inch. The photo is scaled and cropped to that frame.
 */
final class TicketFrame {

	public const DPI = 150;

	public static function width_px(): int {
		return (int) round( Document::PAGE_WIDTH / 72 * self::DPI );
	}

	public static function height_px(): int {
		return (int) round( Document::PAGE_HEIGHT / 72 * self::DPI );
	}

	public static function fit_label(): string {
		return self::width_px() . ' × ' . self::height_px() . ' pixels, stående';
	}

	/**
	 * A chosen ticket image wins. Otherwise the event poster is the ticket image.
	 */
	public static function image_id( int $ticket_image_id, int $poster_id ): int {
		if ( $ticket_image_id > 0 ) {
			return $ticket_image_id;
		}

		return max( 0, $poster_id );
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
}
