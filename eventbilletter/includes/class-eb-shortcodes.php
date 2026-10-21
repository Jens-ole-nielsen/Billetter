<?php
/**
 * Frontend shortcodes.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Shortcodes
 */
class EB_Shortcodes {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'eb_tickets', array( $this, 'tickets' ) );
		add_shortcode( 'eb_checkout_status', array( $this, 'checkout_status' ) );
	}

	/**
	 * [eb_tickets id=""] ticket purchase form.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function tickets( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'eb_tickets'
		);

		$event_id = absint( $atts['id'] );
		if ( ! $event_id ) {
			$event_id = get_the_ID();
		}

		$event = get_post( $event_id );
		if ( ! $event || 'eb_event' !== $event->post_type ) {
			return '<p class="eb-notice eb-notice--error">' . esc_html__( 'Event ikke fundet.', 'eventbilletter' ) . '</p>';
		}

		$types = EB_Tickets::get_types( $event_id );
		$types = array_filter(
			$types,
			function ( $t ) {
				return ! empty( $t['active'] );
			}
		);

		$sales_open = get_post_meta( $event_id, '_eb_sales_open', true );
		if ( '' === $sales_open ) {
			$sales_open = '1';
		}

		$currency = strtoupper( eventbilletter()->get_setting( 'currency', 'dkk' ) );
		$date     = get_post_meta( $event_id, '_eb_event_date', true );
		$time     = get_post_meta( $event_id, '_eb_event_time', true );
		$venue    = get_post_meta( $event_id, '_eb_event_venue', true );

		ob_start();
		include EB_PLUGIN_DIR . 'templates/ticket-form.php';
		return (string) ob_get_clean();
	}

	/**
	 * [eb_checkout_status] after Stripe return.
	 *
	 * @return string
	 */
	public function checkout_status() {
		if ( empty( $_GET['eb_checkout'] ) || empty( $_GET['order_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return '';
		}

		$action   = sanitize_key( wp_unslash( $_GET['eb_checkout'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$order_id = absint( $_GET['order_id'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$order    = EB_Orders::get( $order_id );

		ob_start();
		include EB_PLUGIN_DIR . 'templates/checkout-status.php';
		return (string) ob_get_clean();
	}
}
