<?php
/**
 * QR matrices for tickets.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as Library;
use chillerlan\QRCode\QROptions;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a QR symbol for an unguessable ticket token.
 */
final class QrCode {

	/**
	 * Dark modules, including the quiet zone.
	 *
	 * @return list<list<bool>>
	 */
	public static function matrix( string $data ): array {
		$options = new QROptions(
			array(
				'eccLevel' => EccLevel::M,
				'addQuietzone' => true,
				'quietzoneSize' => 2,
			)
		);
		$matrix = ( new Library( $options ) )->addByteSegment( $data )->getQRMatrix();
		$size = $matrix->size();
		$rows = array();

		for ( $y = 0; $y < $size; $y++ ) {
			$row = array();
			for ( $x = 0; $x < $size; $x++ ) {
				$row[] = $matrix->check( $x, $y );
			}
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * SVG markup for the designer preview. Returns an empty string if the result looks unsafe.
	 */
	public static function svg_markup( string $data ): string {
		$options = new QROptions(
			array(
				'outputType' => QROutputInterface::MARKUP_SVG,
				'outputBase64' => false,
				'eccLevel' => EccLevel::M,
				'addQuietzone' => true,
				'quietzoneSize' => 2,
			)
		);
		$svg = ( new Library( $options ) )->render( $data );
		if ( ! is_string( $svg ) ) {
			return '';
		}
		$stripped = preg_replace( '/<\?xml.*?\?>/s', '', $svg );
		$svg = is_string( $stripped ) ? trim( $stripped ) : '';
		if ( ! str_starts_with( $svg, '<svg' ) || false !== stripos( $svg, '<script' ) ) {
			return '';
		}

		return $svg;
	}
}
