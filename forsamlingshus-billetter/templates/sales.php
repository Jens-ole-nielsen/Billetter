<?php
/**
 * Public sales page.
 *
 * @package ForsamlingshusBilletter
 *
 * @var array<string, mixed> $event
 * @var int                   $remaining
 * @var list<array{id:string,label:string,description:string}> $methods
 * @var string                $result
 * @var string                $error
 * @var object|null           $order
 * @var int                   $max_qty
 */

defined( 'ABSPATH' ) || exit;

use Forsamlingshus\Billetter\Format;
use Forsamlingshus\Billetter\Money;

$errors = array(
	'validation' => __( 'Tjek navn, e-mail, telefon og antal.', 'forsamlingshus-billetter' ),
	'soldout' => __( 'Der er ikke nok billetter tilbage.', 'forsamlingshus-billetter' ),
	'payment' => __( 'Betalingen kunne ikke startes. Prøv igen, eller vælg faktura hvis det er muligt.', 'forsamlingshus-billetter' ),
	'method' => __( 'Den valgte betalingsmåde er ikke tilgængelig.', 'forsamlingshus-billetter' ),
	'closed' => __( 'Salget er lukket.', 'forsamlingshus-billetter' ),
	'stripe_min' => __( 'Beløbet er under 2,50 kr., som er Stripes minimum. Vælg faktura, eller køb flere billetter.', 'forsamlingshus-billetter' ),
	'payment_min' => __( 'Beløbet er under 2,50 kr., som er Stripes minimum. Ordren er ikke oprettet.', 'forsamlingshus-billetter' ),
	'payment_key_missing' => __( 'Stripe-nøglen mangler. Betalingen kan ikke startes, før den er gemt under Billetter → Indstillinger.', 'forsamlingshus-billetter' ),
	'payment_key' => __( 'Stripe afviste nøglen. Tjek den hemmelige nøgle under Billetter → Indstillinger.', 'forsamlingshus-billetter' ),
	'payment_url' => __( 'Stripe afviste adressen på salgssiden. Betalingen er ikke startet.', 'forsamlingshus-billetter' ),
	'config' => __( 'Online betaling er ikke sat op endnu.', 'forsamlingshus-billetter' ),
);

$title = (string) $event['title'];
$date = Format::date( (string) $event['date'] );
$time = Format::time( (string) $event['time'] );
$price = Money::format_ore( (int) $event['price_ore'] );
$free = 0 === (int) $event['price_ore'];
$show_form = $remaining > 0 && ! in_array( $result, array( 'success', 'invoice' ), true );
$description = (string) $event['description'];
if ( function_exists( 'do_blocks' ) ) {
	$description = do_blocks( $description );
}
$description = wpautop( $description );
?>
<div class="fhb-wrap">
	<?php if ( ! empty( $event['poster_id'] ) ) : ?>
		<?php echo wp_get_attachment_image( (int) $event['poster_id'], 'large', false, array( 'class' => 'fhb-poster', 'alt' => $title ) ); ?>
	<?php endif; ?>

	<h2 class="fhb-title"><?php echo esc_html( $title ); ?></h2>
	<ul class="fhb-meta">
		<?php if ( '' !== $date ) : ?>
			<li><?php echo esc_html( $date ); ?></li>
		<?php endif; ?>
		<?php if ( '' !== $time ) : ?>
			<li><?php echo esc_html( sprintf( /* translators: %s: time */ __( 'Kl. %s', 'forsamlingshus-billetter' ), $time ) ); ?></li>
		<?php endif; ?>
	</ul>
	<p class="fhb-price"><?php echo esc_html( $free ? __( 'Gratis', 'forsamlingshus-billetter' ) : $price ); ?></p>
	<?php if ( $remaining > 0 ) : ?>
		<p class="fhb-remaining">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of tickets */
					_n( '%d billet tilbage', '%d billetter tilbage', $remaining, 'forsamlingshus-billetter' ),
					$remaining
				)
			);
			?>
		</p>
	<?php elseif ( (int) $event['capacity'] < 1 ) : ?>
		<p class="fhb-soldout"><?php esc_html_e( 'Salget er ikke åbnet endnu.', 'forsamlingshus-billetter' ); ?></p>
	<?php else : ?>
		<p class="fhb-soldout"><?php esc_html_e( 'Udsolgt', 'forsamlingshus-billetter' ); ?></p>
	<?php endif; ?>

	<div class="fhb-description"><?php echo wp_kses_post( $description ); ?></div>

	<?php if ( isset( $errors[ $error ] ) ) : ?>
		<p class="fhb-error" role="alert"><?php echo esc_html( $errors[ $error ] ); ?></p>
	<?php endif; ?>

	<?php if ( 'success' === $result ) : ?>
		<div class="fhb-success" role="status">
			<p><strong><?php esc_html_e( 'Tak for din bestilling.', 'forsamlingshus-billetter' ); ?></strong></p>
			<?php if ( $order && 'paid' === (string) $order->status && ! empty( $order->tickets_sent_at ) ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: buyer email */
							__( 'Billetterne er sendt til %s. Kig også i mappen med uønsket mail.', 'forsamlingshus-billetter' ),
							(string) $order->buyer_email
						)
					);
					?>
				</p>
			<?php elseif ( $order && 'paid' === (string) $order->status ) : ?>
				<p><?php esc_html_e( 'Betalingen er registreret. Hvis billetterne ikke er kommet på e-mail inden længe, så kontakt forsamlingshuset.', 'forsamlingshus-billetter' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Når betalingen er bekræftet, sender vi billetterne til den e-mail, du opgav.', 'forsamlingshus-billetter' ); ?></p>
			<?php endif; ?>
		</div>
	<?php elseif ( 'invoice' === $result ) : ?>
		<div class="fhb-success" role="status">
			<p><strong><?php esc_html_e( 'Tak for din bestilling.', 'forsamlingshus-billetter' ); ?></strong></p>
			<p><?php esc_html_e( 'Du har valgt betaling via faktura efter aftale. Billetterne bliver sendt, når forsamlingshuset har registreret betalingen.', 'forsamlingshus-billetter' ); ?></p>
		</div>
	<?php elseif ( 'cancel' === $result ) : ?>
		<p class="fhb-error" role="status"><?php esc_html_e( 'Betalingen blev afbrudt. Pladserne frigives med det samme, eller senest når betalingslinket udløber efter ca. 30 minutter.', 'forsamlingshus-billetter' ); ?></p>
	<?php endif; ?>

	<?php if ( $show_form ) : ?>
		<form class="fhb-form fhb-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-invoice-confirm="<?php echo esc_attr__( 'Betaling via faktura skal være aftalt med forsamlingshuset på forhånd. Vil du fortsætte med faktura?', 'forsamlingshus-billetter' ); ?>">
			<input type="hidden" name="action" value="fhb_checkout">
			<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event['id'] ); ?>">
			<?php wp_nonce_field( 'fhb_checkout_' . (int) $event['id'], 'fhb_checkout_nonce' ); ?>
			<p class="fhb-hp" aria-hidden="true">
				<label for="fhb-company"><?php esc_html_e( 'Firma', 'forsamlingshus-billetter' ); ?></label>
				<input type="text" id="fhb-company" name="fhb_company" tabindex="-1" autocomplete="off">
			</p>
			<label for="fhb-name"><?php esc_html_e( 'Navn', 'forsamlingshus-billetter' ); ?></label>
			<input type="text" id="fhb-name" name="buyer_name" required maxlength="190" autocomplete="name">

			<label for="fhb-email"><?php esc_html_e( 'E-mail', 'forsamlingshus-billetter' ); ?></label>
			<input type="email" id="fhb-email" name="buyer_email" required maxlength="190" autocomplete="email">

			<label for="fhb-phone"><?php esc_html_e( 'Telefon', 'forsamlingshus-billetter' ); ?></label>
			<input type="tel" id="fhb-phone" name="buyer_phone" required maxlength="40" autocomplete="tel" inputmode="tel">

			<label for="fhb-qty"><?php esc_html_e( 'Antal billetter', 'forsamlingshus-billetter' ); ?></label>
			<input type="number" id="fhb-qty" name="quantity" required min="1" max="<?php echo esc_attr( (string) $max_qty ); ?>" value="1">
			<p class="description"><?php esc_html_e( 'Navnet skrives på alle billetterne i ordren. Der skal ikke udfyldes et navn pr. billet.', 'forsamlingshus-billetter' ); ?></p>

			<label for="fhb-remark"><?php esc_html_e( 'Bemærkning til ordren (valgfri)', 'forsamlingshus-billetter' ); ?></label>
			<textarea id="fhb-remark" name="remark" rows="3" maxlength="500" placeholder="<?php esc_attr_e( 'F.eks. hvis I gerne vil sidde sammen med et andet selskab.', 'forsamlingshus-billetter' ); ?>"></textarea>

			<?php if ( ! $free && count( $methods ) > 1 ) : ?>
				<fieldset>
					<legend><?php esc_html_e( 'Betaling', 'forsamlingshus-billetter' ); ?></legend>
					<?php foreach ( $methods as $method ) : ?>
						<label class="fhb-choice">
							<input type="radio" name="payment_method" value="<?php echo esc_attr( $method['id'] ); ?>" <?php checked( 'stripe' === $method['id'] ); ?> required>
							<span><?php echo esc_html( $method['label'] ); ?></span>
						</label>
						<p class="description"><?php echo esc_html( $method['description'] ); ?></p>
					<?php endforeach; ?>
				</fieldset>
			<?php elseif ( ! $free && 1 === count( $methods ) && 'stripe' === $methods[0]['id'] ) : ?>
				<input type="hidden" name="payment_method" value="stripe">
				<p class="description"><?php echo esc_html( $methods[0]['description'] ); ?></p>
			<?php elseif ( ! $free ) : ?>
				<p class="fhb-error"><?php esc_html_e( 'Der er ingen betalingsmåde slået til for dette arrangement.', 'forsamlingshus-billetter' ); ?></p>
			<?php endif; ?>

			<?php if ( $free || array() !== $methods ) : ?>
				<button type="submit"><?php echo esc_html( $free ? __( 'Hent gratis billetter', 'forsamlingshus-billetter' ) : __( 'Gå til betaling', 'forsamlingshus-billetter' ) ); ?></button>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Vi bruger navn, e-mail og telefon til at sende billetterne og til indtjekning.', 'forsamlingshus-billetter' ); ?></p>
		</form>
	<?php elseif ( $remaining > 0 && in_array( $result, array( 'success', 'invoice' ), true ) ) : ?>
		<p><a href="<?php echo esc_url( remove_query_arg( array( 'fhb_result', 'order', 'fhb_error' ) ) ); ?>"><?php esc_html_e( 'Køb flere billetter', 'forsamlingshus-billetter' ); ?></a></p>
	<?php endif; ?>
</div>
