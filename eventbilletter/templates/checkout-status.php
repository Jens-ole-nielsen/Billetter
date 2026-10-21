<?php
/**
 * Checkout return status template.
 *
 * Variables: $action, $order_id, $order
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

if ( ! $order ) {
	echo '<div class="eb-status-box eb-status-box--error"><p>' . esc_html__( 'Ordren blev ikke fundet.', 'eventbilletter' ) . '</p></div>';
	return;
}

$event_title = get_the_title( $order['event_id'] );

if ( 'cancel' === $action ) {
	?>
	<div class="eb-status-box eb-status-box--cancel">
		<h2><?php esc_html_e( 'Betaling annulleret', 'eventbilletter' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: event title */
				esc_html__( 'Din betaling til %s blev ikke gennemført. Du kan prøve igen når du er klar.', 'eventbilletter' ),
				esc_html( $event_title )
			);
			?>
		</p>
		<?php if ( $order['event_id'] ) : ?>
			<p><a class="eb-btn" href="<?php echo esc_url( get_permalink( $order['event_id'] ) ); ?>"><?php esc_html_e( 'Tilbage til eventet', 'eventbilletter' ); ?></a></p>
		<?php endif; ?>
	</div>
	<?php
	return;
}
?>
<div class="eb-status-box eb-status-box--success">
	<?php if ( EB_Orders::STATUS_PAID === $order['status'] ) : ?>
		<h2><?php esc_html_e( 'Tak for dit køb!', 'eventbilletter' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: event title, 2: email */
				esc_html__( 'Dine billetter til %1$s er bekræftet. Vi har sendt billetkoderne til %2$s.', 'eventbilletter' ),
				esc_html( $event_title ),
				esc_html( $order['customer_email'] )
			);
			?>
		</p>
		<p><strong><?php esc_html_e( 'Ordrenummer:', 'eventbilletter' ); ?></strong> <?php echo esc_html( $order['order_number'] ); ?></p>

		<?php if ( ! empty( $order['ticket_codes'] ) && is_array( $order['ticket_codes'] ) ) : ?>
			<ul class="eb-ticket-codes">
				<?php foreach ( $order['ticket_codes'] as $group ) : ?>
					<?php foreach ( (array) ( $group['codes'] ?? array() ) as $code ) : ?>
						<li>
							<span class="eb-ticket-codes__type"><?php echo esc_html( $group['type_name'] ?? '' ); ?></span>
							<code><?php echo esc_html( $code ); ?></code>
						</li>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php else : ?>
		<h2><?php esc_html_e( 'Vi behandler din betaling…', 'eventbilletter' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: email */
				esc_html__( 'Hvis betalingen lykkes, sender vi billetterne til %s inden for få minutter.', 'eventbilletter' ),
				esc_html( $order['customer_email'] )
			);
			?>
		</p>
		<p><strong><?php esc_html_e( 'Ordrenummer:', 'eventbilletter' ); ?></strong> <?php echo esc_html( $order['order_number'] ); ?></p>
	<?php endif; ?>
</div>
