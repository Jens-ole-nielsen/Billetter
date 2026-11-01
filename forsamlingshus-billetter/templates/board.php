<?php
/**
 * Phone sales board. Values are already escaped by the caller only through this template.
 *
 * @package ForsamlingshusBilletter
 *
 * @var string $refreshed
 * @var string $refresh_url
 * @var string $sold
 * @var string $remaining
 * @var string $amount
 * @var list<array{title:string,when:string,sold:string,remaining:string,amount:string,reserved:string}> $events
 * @var list<array{number:string,buyer:string,event:string,quantity:string,amount:string,status:string,when:string}> $orders
 */

defined( 'ABSPATH' ) || exit;
?>
<header class="fhb-board-top">
	<div>
		<p class="fhb-board-kicker"><?php esc_html_e( 'Billetsalg', 'forsamlingshus-billetter' ); ?></p>
		<p class="fhb-board-time"><?php echo esc_html( sprintf( /* translators: %s: time */ __( 'Opdateret kl. %s', 'forsamlingshus-billetter' ), $refreshed ) ); ?></p>
	</div>
	<a class="fhb-board-refresh" href="<?php echo esc_url( $refresh_url ); ?>"><?php esc_html_e( 'Opdatér', 'forsamlingshus-billetter' ); ?></a>
</header>

<section class="fhb-board-totals" aria-label="<?php esc_attr_e( 'Samlet', 'forsamlingshus-billetter' ); ?>">
	<p><span><?php esc_html_e( 'Solgt', 'forsamlingshus-billetter' ); ?></span><strong><?php echo esc_html( $sold ); ?></strong></p>
	<p><span><?php esc_html_e( 'Ledige', 'forsamlingshus-billetter' ); ?></span><strong><?php echo esc_html( $remaining ); ?></strong></p>
	<p><span><?php esc_html_e( 'Beløb', 'forsamlingshus-billetter' ); ?></span><strong><?php echo esc_html( $amount ); ?></strong></p>
</section>

<?php if ( array() === $events ) : ?>
	<p class="fhb-board-empty"><?php esc_html_e( 'Ingen aktuelle arrangementer.', 'forsamlingshus-billetter' ); ?></p>
<?php else : ?>
	<?php foreach ( $events as $event ) : ?>
		<article class="fhb-board-event">
			<h2><?php echo esc_html( $event['title'] ); ?></h2>
			<?php if ( '' !== $event['when'] ) : ?>
				<p class="fhb-board-when"><?php echo esc_html( $event['when'] ); ?></p>
			<?php endif; ?>
			<dl>
				<div><dt><?php esc_html_e( 'Solgt', 'forsamlingshus-billetter' ); ?></dt><dd><?php echo esc_html( $event['sold'] ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Ledige', 'forsamlingshus-billetter' ); ?></dt><dd><?php echo esc_html( $event['remaining'] ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Beløb', 'forsamlingshus-billetter' ); ?></dt><dd><?php echo esc_html( $event['amount'] ); ?></dd></div>
			</dl>
			<?php if ( '' !== $event['reserved'] ) : ?>
				<p class="fhb-board-hold"><?php echo esc_html( sprintf( /* translators: %s: reserved tickets */ __( 'Reserveret: %s', 'forsamlingshus-billetter' ), $event['reserved'] ) ); ?></p>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
<?php endif; ?>

<section class="fhb-board-orders" aria-label="<?php esc_attr_e( 'Seneste ordrer', 'forsamlingshus-billetter' ); ?>">
	<h2><?php esc_html_e( 'Seneste ordrer', 'forsamlingshus-billetter' ); ?></h2>
	<?php if ( array() === $orders ) : ?>
		<p class="fhb-board-empty"><?php esc_html_e( 'Ingen ordrer endnu.', 'forsamlingshus-billetter' ); ?></p>
	<?php else : ?>
		<?php foreach ( $orders as $order ) : ?>
			<article class="fhb-board-order">
				<p class="fhb-board-order-line"><strong><?php echo esc_html( $order['number'] ); ?></strong> <span><?php echo esc_html( $order['status'] ); ?></span></p>
				<p><?php echo esc_html( $order['buyer'] ); ?> · <?php echo esc_html( $order['quantity'] ); ?> · <?php echo esc_html( $order['amount'] ); ?></p>
				<p class="fhb-board-when"><?php echo esc_html( $order['event'] ); ?></p>
				<?php if ( '' !== $order['when'] ) : ?>
					<p class="fhb-board-when"><?php echo esc_html( $order['when'] ); ?></p>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
