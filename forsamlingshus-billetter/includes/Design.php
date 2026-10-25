<?php
/**
 * Ticket design options.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Practical ticket setup: logo, heading, colors and which fields to print.
 */
final class Design {

	public const OPTION = 'fhb_design';

	/**
	 * @return array<string, int|string>
	 */
	public static function defaults(): array {
		return array(
			'logo_id' => 0,
			'heading' => 'Adgangsbillet',
			'color_primary' => '#1F4B3A',
			'color_text' => '#1C1C1C',
			'color_background' => '#FFFBF5',
			'show_event' => 1,
			'show_date' => 1,
			'show_time' => 1,
			'show_buyer' => 1,
			'show_price' => 1,
			'show_qr' => 1,
		);
	}

	/**
	 * @return array<string, int|string>
	 */
	public function get(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$design = array_merge( self::defaults(), $stored );
		$design['logo_id'] = absint( $design['logo_id'] );
		$design['heading'] = sanitize_text_field( (string) $design['heading'] );
		if ( '' === $design['heading'] ) {
			$design['heading'] = (string) self::defaults()['heading'];
		}
		foreach ( array( 'color_primary', 'color_text', 'color_background' ) as $key ) {
			$color = strtoupper( (string) $design[ $key ] );
			if ( 1 !== preg_match( '/^#[0-9A-F]{6}$/', $color ) ) {
				$color = (string) self::defaults()[ $key ];
			}
			$design[ $key ] = $color;
		}
		foreach ( array( 'show_event', 'show_date', 'show_time', 'show_buyer', 'show_price', 'show_qr' ) as $flag ) {
			$design[ $flag ] = empty( $design[ $flag ] ) ? 0 : 1;
		}

		return $design;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function save( array $input ): void {
		$design = self::defaults();
		$design['logo_id'] = absint( $input['logo_id'] ?? 0 );
		if ( $design['logo_id'] && ! wp_attachment_is_image( $design['logo_id'] ) ) {
			$design['logo_id'] = 0;
		}
		$heading = sanitize_text_field( (string) ( $input['heading'] ?? '' ) );
		$design['heading'] = '' !== $heading ? mb_substr( $heading, 0, 80 ) : (string) self::defaults()['heading'];
		foreach ( array( 'color_primary', 'color_text', 'color_background' ) as $key ) {
			$color = strtoupper( sanitize_text_field( (string) ( $input[ $key ] ?? '' ) ) );
			if ( 1 === preg_match( '/^#[0-9A-F]{6}$/', $color ) ) {
				$design[ $key ] = $color;
			}
		}
		foreach ( array( 'show_event', 'show_date', 'show_time', 'show_buyer', 'show_price', 'show_qr' ) as $flag ) {
			$design[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}
		update_option( self::OPTION, $design, false );
	}
}
