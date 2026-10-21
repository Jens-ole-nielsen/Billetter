<?php
/**
 * Order confirmation emails.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Emails
 */
class EB_Emails {

	/**
	 * Send ticket confirmation to customer.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public static function send_confirmation( $order_id ) {
		$order = EB_Orders::get( $order_id );
		if ( ! $order || empty( $order['customer_email'] ) ) {
			return false;
		}

		$event = get_post( $order['event_id'] );
		$event_title = $event ? $event->post_title : __( 'Event', 'eventbilletter' );
		$event_date  = get_post_meta( $order['event_id'], '_eb_event_date', true );
		$event_venue = get_post_meta( $order['event_id'], '_eb_event_venue', true );

		$subject = sprintf(
			/* translators: 1: order number, 2: event title */
			__( 'Dine billetter — %1$s (%2$s)', 'eventbilletter' ),
			$event_title,
			$order['order_number']
		);

		$lines = array();
		$lines[] = sprintf(
			/* translators: %s: customer name */
			__( 'Hej %s,', 'eventbilletter' ),
			$order['customer_name']
		);
		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %s: event title */
			__( 'Tak for dit køb! Her er dine billetter til %s.', 'eventbilletter' ),
			$event_title
		);
		$lines[] = '';
		$lines[] = __( 'Ordrenummer:', 'eventbilletter' ) . ' ' . $order['order_number'];

		if ( $event_date ) {
			$lines[] = __( 'Dato:', 'eventbilletter' ) . ' ' . $event_date;
		}
		if ( $event_venue ) {
			$lines[] = __( 'Sted:', 'eventbilletter' ) . ' ' . $event_venue;
		}

		$lines[] = '';
		$lines[] = __( 'Billetter:', 'eventbilletter' );

		if ( is_array( $order['ticket_codes'] ) ) {
			foreach ( $order['ticket_codes'] as $group ) {
				$name  = isset( $group['type_name'] ) ? $group['type_name'] : '';
				$codes = isset( $group['codes'] ) && is_array( $group['codes'] ) ? $group['codes'] : array();
				foreach ( $codes as $code ) {
					$lines[] = '  • ' . $name . ': ' . $code;
				}
			}
		}

		$lines[] = '';
		$lines[] = sprintf(
			/* translators: 1: amount, 2: currency */
			__( 'Total: %1$s %2$s', 'eventbilletter' ),
			number_format_i18n( $order['total'], 2 ),
			strtoupper( $order['currency'] )
		);
		$lines[] = '';
		$lines[] = __( 'Vis denne e-mail (eller billetkoderne) ved indgangen.', 'eventbilletter' );
		$lines[] = '';
		$lines[] = get_bloginfo( 'name' );

		$body = implode( "\n", $lines );

		$from_email = eventbilletter()->get_setting( 'from_email', get_option( 'admin_email' ) );
		$from_name  = eventbilletter()->get_setting( 'from_name', get_bloginfo( 'name' ) );

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);

		$sent = wp_mail( $order['customer_email'], $subject, $body, $headers );

		// Notify admin.
		$admin = get_option( 'admin_email' );
		if ( $admin && $admin !== $order['customer_email'] ) {
			$admin_subject = sprintf(
				/* translators: %s: order number */
				__( '[Eventbilletter] Ny ordre %s', 'eventbilletter' ),
				$order['order_number']
			);
			$admin_body = sprintf(
				"%s\n%s\n%s <%s>\n%s %s %s\n",
				$event_title,
				$order['order_number'],
				$order['customer_name'],
				$order['customer_email'],
				number_format_i18n( $order['total'], 2 ),
				strtoupper( $order['currency'] ),
				admin_url( 'post.php?post=' . $order_id . '&action=edit' )
			);
			wp_mail( $admin, $admin_subject, $admin_body, $headers );
		}

		return (bool) $sent;
	}
}
