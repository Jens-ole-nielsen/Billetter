<?php
/**
 * Turns a media-library image into a JPEG the PDF writer can embed.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * GD is part of a normal WordPress host. Without it, tickets are still sent without the logo.
 */
final class Images {

	/**
	 * @return array{data:string,w:int,h:int}|null
	 */
	public static function jpeg_from_attachment( int $attachment_id, int $max_edge = 1200 ): ?array {
		if ( $attachment_id < 1 || ! function_exists( 'imagecreatefromstring' ) ) {
			return null;
		}
		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || ! is_file( $path ) ) {
			return null;
		}
		$raw = file_get_contents( $path );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$image = @imagecreatefromstring( $raw );
		if ( ! $image ) {
			return null;
		}

		$width = imagesx( $image );
		$height = imagesy( $image );
		if ( $width < 1 || $height < 1 ) {
			imagedestroy( $image );
			return null;
		}

		$scale = min( 1, $max_edge / max( $width, $height ) );
		$new_w = max( 1, (int) round( $width * $scale ) );
		$new_h = max( 1, (int) round( $height * $scale ) );
		$flat = imagecreatetruecolor( $new_w, $new_h );
		if ( ! $flat ) {
			imagedestroy( $image );
			return null;
		}
		$white = imagecolorallocate( $flat, 255, 255, 255 );
		imagefill( $flat, 0, 0, $white );
		imagecopyresampled( $flat, $image, 0, 0, 0, 0, $new_w, $new_h, $width, $height );

		ob_start();
		imagejpeg( $flat, null, 85 );
		$jpeg = ob_get_clean();
		imagedestroy( $image );
		imagedestroy( $flat );
		if ( ! is_string( $jpeg ) || '' === $jpeg ) {
			return null;
		}

		return array(
			'data' => $jpeg,
			'w' => $new_w,
			'h' => $new_h,
		);
	}

	/**
	 * JPEG cropped and scaled so it fills the ticket page.
	 *
	 * @return array{data:string,w:int,h:int}|null
	 */
	public static function jpeg_cover( int $attachment_id, ?int $dst_w = null, ?int $dst_h = null ): ?array {
		if ( $attachment_id < 1 || ! function_exists( 'imagecreatefromstring' ) ) {
			return null;
		}
		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || ! is_file( $path ) ) {
			return null;
		}
		$raw = file_get_contents( $path );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$image = @imagecreatefromstring( $raw );
		if ( ! $image ) {
			return null;
		}

		$width = imagesx( $image );
		$height = imagesy( $image );
		if ( $width < 1 || $height < 1 ) {
			imagedestroy( $image );
			return null;
		}

		$dst_w = $dst_w ?? TicketFrame::width_px();
		$dst_h = $dst_h ?? TicketFrame::height_px();
		if ( $dst_w < 1 || $dst_h < 1 ) {
			imagedestroy( $image );
			return null;
		}
		$crop = TicketFrame::cover_crop( $width, $height, $dst_w, $dst_h );
		$flat = imagecreatetruecolor( $dst_w, $dst_h );
		if ( ! $flat ) {
			imagedestroy( $image );
			return null;
		}
		$white = imagecolorallocate( $flat, 255, 255, 255 );
		imagefill( $flat, 0, 0, $white );
		imagecopyresampled(
			$flat,
			$image,
			0,
			0,
			$crop['x'],
			$crop['y'],
			$dst_w,
			$dst_h,
			$crop['w'],
			$crop['h']
		);

		ob_start();
		imagejpeg( $flat, null, 82 );
		$jpeg = ob_get_clean();
		imagedestroy( $image );
		imagedestroy( $flat );
		if ( ! is_string( $jpeg ) || '' === $jpeg ) {
			return null;
		}

		return array(
			'data' => $jpeg,
			'w' => $dst_w,
			'h' => $dst_h,
		);
	}
}
