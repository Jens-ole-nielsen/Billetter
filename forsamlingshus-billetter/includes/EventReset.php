<?php
/**
 * Clears purchases for one event.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

use Forsamlingshus\Billetter\Admin\Notices;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes orders and tickets for a single event and returns its stock to zero sold.
 *
 * Money already captured in Stripe is not refunded.
 */
final class EventReset {

	public function __construct( private Stock $stock ) {
	}

	public function hooks(): void {
		add_action( 'admin_post_fhb_reset_event', array( $this, 'handle' ) );
	}

	public function handle(): void {
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		if ( ! Capabilities::can( Capabilities::ORDERS ) || ! current_user_can( 'edit_post', $event_id ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'fhb_reset_event_' . $event_id );

		if ( $event_id < 1 || 'fhb_event' !== get_post_type( $event_id ) ) {
			Notices::flash( 'error', __( 'Arrangementet blev ikke fundet. Der er ikke slettet noget.', 'forsamlingshus-billetter' ) );
			wp_safe_redirect( admin_url( 'edit.php?post_type=fhb_event' ) );
			exit;
		}

		if ( ! $this->clear( $event_id ) ) {
			Notices::flash( 'error', __( 'Nulstillingen kunne ikke gennemføres. Der er ikke slettet noget.', 'forsamlingshus-billetter' ) );
		} else {
			Notices::flash( 'success', __( 'Ordrer og billetter til arrangementet er slettet. Salget er nulstillet. Beløb, der allerede er trukket i Stripe, er ikke refunderet.', 'forsamlingshus-billetter' ) );
		}

		wp_safe_redirect( admin_url( 'post.php?post=' . $event_id . '&action=edit' ) );
		exit;
	}

	public function clear( int $event_id ): bool {
		global $wpdb;

		$orders = Database::orders_table();
		$tickets = Database::tickets_table();
		$stock = Database::stock_table();

		$wpdb->query( 'START TRANSACTION' );
		$deleted_tickets = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$tickets} WHERE event_id = %d",
				$event_id
			)
		);
		$deleted_by_order = $wpdb->query(
			$wpdb->prepare(
				"DELETE t FROM {$tickets} t INNER JOIN {$orders} o ON o.id = t.order_id WHERE o.event_id = %d",
				$event_id
			)
		);
		$deleted_orders = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$orders} WHERE event_id = %d",
				$event_id
			)
		);
		if ( false === $deleted_tickets || false === $deleted_by_order || false === $deleted_orders ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$row = $this->stock->get( $event_id );
		if ( $row ) {
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$stock} SET reserved = 0, sold = 0 WHERE event_id = %d",
					$event_id
				)
			);
			if ( false === $updated ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
		}
		$wpdb->query( 'COMMIT' );

		if ( ! $row ) {
			$capacity = (int) get_post_meta( $event_id, '_fhb_capacity', true );
			$this->stock->sync( $event_id, $capacity );
		}

		return true;
	}
}
