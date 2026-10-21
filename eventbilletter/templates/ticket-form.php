<?php
/**
 * Ticket purchase form template.
 *
 * Variables: $event_id, $event, $types, $sales_open, $currency, $date, $time, $venue
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="eb-ticket-form" data-event-id="<?php echo esc_attr( (string) $event_id ); ?>" data-currency="<?php echo esc_attr( $currency ); ?>">
	<?php if ( $date || $time || $venue ) : ?>
		<div class="eb-event-meta">
			<?php if ( $date ) : ?>
				<span class="eb-event-meta__item">
					<strong><?php esc_html_e( 'Dato', 'eventbilletter' ); ?>:</strong>
					<?php echo esc_html( $date ); ?>
					<?php if ( $time ) : ?>
						<?php echo esc_html( ' · ' . $time ); ?>
					<?php endif; ?>
				</span>
			<?php endif; ?>
			<?php if ( $venue ) : ?>
				<span class="eb-event-meta__item">
					<strong><?php esc_html_e( 'Sted', 'eventbilletter' ); ?>:</strong>
					<?php echo esc_html( $venue ); ?>
				</span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( '1' !== (string) $sales_open ) : ?>
		<p class="eb-notice"><?php esc_html_e( 'Billetsalg er lukket for dette event.', 'eventbilletter' ); ?></p>
	<?php elseif ( empty( $types ) ) : ?>
		<p class="eb-notice"><?php esc_html_e( 'Der er ingen billetter til salg endnu.', 'eventbilletter' ); ?></p>
	<?php elseif ( ! EB_Stripe::is_configured() ) : ?>
		<p class="eb-notice eb-notice--error"><?php esc_html_e( 'Betaling er midlertidigt utilgængelig.', 'eventbilletter' ); ?></p>
	<?php else : ?>
		<form class="eb-form" novalidate>
			<div class="eb-form__tickets">
				<?php foreach ( $types as $type ) : ?>
					<?php
					$remaining = EB_Tickets::remaining( $type );
					$sold_out  = ( 0 === $remaining );
					$max       = ( -1 === $remaining ) ? 20 : min( 20, $remaining );
					?>
					<div class="eb-ticket-option <?php echo $sold_out ? 'is-sold-out' : ''; ?>">
						<div class="eb-ticket-option__info">
							<div class="eb-ticket-option__name"><?php echo esc_html( $type['name'] ); ?></div>
							<?php if ( ! empty( $type['description'] ) ) : ?>
								<div class="eb-ticket-option__desc"><?php echo esc_html( $type['description'] ); ?></div>
							<?php endif; ?>
							<div class="eb-ticket-option__price">
								<?php echo esc_html( number_format_i18n( (float) $type['price'], 2 ) . ' ' . $currency ); ?>
								<?php if ( -1 !== $remaining ) : ?>
									<span class="eb-ticket-option__stock">
										<?php
										if ( $sold_out ) {
											esc_html_e( 'Udsolgt', 'eventbilletter' );
										} else {
											printf(
												/* translators: %d: remaining */
												esc_html__( '%d tilbage', 'eventbilletter' ),
												(int) $remaining
											);
										}
										?>
									</span>
								<?php endif; ?>
							</div>
						</div>
						<div class="eb-ticket-option__qty">
							<label class="screen-reader-text" for="eb-qty-<?php echo esc_attr( $type['id'] ); ?>">
								<?php echo esc_html( sprintf( /* translators: %s: ticket name */ __( 'Antal: %s', 'eventbilletter' ), $type['name'] ) ); ?>
							</label>
							<input
								type="number"
								id="eb-qty-<?php echo esc_attr( $type['id'] ); ?>"
								class="eb-qty"
								name="quantities[<?php echo esc_attr( $type['id'] ); ?>]"
								min="0"
								max="<?php echo esc_attr( (string) $max ); ?>"
								value="0"
								data-price="<?php echo esc_attr( (string) $type['price'] ); ?>"
								<?php disabled( $sold_out ); ?>
							/>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="eb-form__customer">
				<label>
					<span><?php esc_html_e( 'Navn', 'eventbilletter' ); ?></span>
					<input type="text" name="customer_name" required autocomplete="name" />
				</label>
				<label>
					<span><?php esc_html_e( 'E-mail', 'eventbilletter' ); ?></span>
					<input type="email" name="customer_email" required autocomplete="email" />
				</label>
			</div>

			<div class="eb-form__footer">
				<div class="eb-form__total">
					<span><?php esc_html_e( 'Total', 'eventbilletter' ); ?></span>
					<strong class="eb-total-amount">0,00 <?php echo esc_html( $currency ); ?></strong>
				</div>
				<button type="submit" class="eb-btn eb-btn--primary" disabled>
					<?php esc_html_e( 'Gå til betaling', 'eventbilletter' ); ?>
				</button>
			</div>

			<p class="eb-form__message" hidden></p>
			<p class="eb-form__secure"><?php esc_html_e( 'Sikker betaling via Stripe. Du sendes videre til Stripe Checkout.', 'eventbilletter' ); ?></p>
		</form>
	<?php endif; ?>
</div>
