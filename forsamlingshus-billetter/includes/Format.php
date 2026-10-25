<?php
/**
 * Danish date formatting in the site timezone.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Formats dates that were saved as site-local MySQL values.
 */
final class Format {

	public static function date( string $ymd ): string {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
			return '';
		}
		$dt = date_create_immutable( $ymd . ' 12:00:00', wp_timezone() );
		if ( ! $dt ) {
			return '';
		}

		return wp_date( 'l j. F Y', $dt->getTimestamp() );
	}

	public static function time( string $hi ): string {
		if ( 1 !== preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hi ) ) {
			return '';
		}

		return $hi;
	}

	public static function when( ?string $mysql ): string {
		if ( null === $mysql || '' === $mysql || str_starts_with( $mysql, '0000-00-00' ) ) {
			return '';
		}
		$dt = date_create_immutable( $mysql, wp_timezone() );
		if ( ! $dt ) {
			return '';
		}

		return wp_date( 'j. M Y H:i', $dt->getTimestamp() );
	}

	public static function clock( ?string $mysql ): string {
		if ( null === $mysql || '' === $mysql || str_starts_with( $mysql, '0000-00-00' ) ) {
			return '';
		}
		$dt = date_create_immutable( $mysql, wp_timezone() );
		if ( ! $dt ) {
			return '';
		}

		return wp_date( 'H:i', $dt->getTimestamp() );
	}
}
