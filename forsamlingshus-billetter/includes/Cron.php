<?php
/**
 * Releases abandoned Stripe holds.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Hourly cleanup. Invoice orders are left alone until an admin cancels or marks them paid.
 */
final class Cron {

	public function __construct( private Fulfillment $fulfillment ) {
	}

	public function hooks(): void {
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( 'fhb_expire_pending', array( $this->fulfillment, 'expire_due' ) );
	}

	public function schedule(): void {
		if ( ! wp_next_scheduled( 'fhb_expire_pending' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'fhb_expire_pending' );
		}
	}
}
