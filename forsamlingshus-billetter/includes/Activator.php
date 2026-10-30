<?php
/**
 * Activation and deactivation.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Creates tables and the door-staff role. Does not delete orders.
 */
final class Activator {

	public static function activate(): void {
		Database::install();
		Capabilities::ensure();
		if ( ! wp_next_scheduled( 'fhb_expire_pending' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'fhb_expire_pending' );
		}
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'fhb_expire_pending' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'fhb_expire_pending' );
		}
		flush_rewrite_rules();
	}
}
