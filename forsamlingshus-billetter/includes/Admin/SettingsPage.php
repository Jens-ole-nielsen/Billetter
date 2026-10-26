<?php
/**
 * Stripe settings.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Admin;

use Forsamlingshus\Billetter\Capabilities;
use Forsamlingshus\Billetter\Mail\MailPoetBody;
use Forsamlingshus\Billetter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Publishable key, secret key and webhook secret. Empty secret fields keep the saved value.
 */
final class SettingsPage {

	public function __construct( private Settings $settings ) {
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_fhb_save_settings', array( $this, 'save' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'fhb-tickets',
			__( 'Indstillinger', 'forsamlingshus-billetter' ),
			__( 'Indstillinger', 'forsamlingshus-billetter' ),
			Capabilities::SETTINGS,
			'fhb-settings',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! Capabilities::can( Capabilities::SETTINGS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		$values = $this->settings->all();
		$webhook = rest_url( 'forsamlingshus-billetter/v1/stripe-webhook' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Indstillinger', 'forsamlingshus-billetter' ); ?></h1>
			<p><?php esc_html_e( 'Betaling sker i Stripe Checkout. Pluginnet rører ikke kortoplysninger. MobilePay og andre metoder slås til i Stripe-dashboardet — pluginnet beder om automatiske betalingsmetoder.', 'forsamlingshus-billetter' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fhb_save_settings' ); ?>
				<input type="hidden" name="action" value="fhb_save_settings">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fhb-pk"><?php esc_html_e( 'Publishable key', 'forsamlingshus-billetter' ); ?></label></th>
						<td>
							<input class="regular-text" type="text" id="fhb-pk" name="publishable_key" value="<?php echo esc_attr( $values['publishable_key'] ); ?>" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Starter med pk_test_ eller pk_live_. Checkout er hosted, så nøglen bruges ikke på salgssiden. Den gemmes, så nøglesættet er samlet.', 'forsamlingshus-billetter' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fhb-sk"><?php esc_html_e( 'Secret key', 'forsamlingshus-billetter' ); ?></label></th>
						<td>
							<input class="regular-text" type="password" id="fhb-sk" name="secret_key" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( '' !== $values['secret_key'] ? __( 'En nøgle er gemt', 'forsamlingshus-billetter' ) : '' ); ?>">
							<?php if ( '' !== $values['secret_key'] ) : ?>
								<label><input type="checkbox" name="clear_secret" value="1"> <?php esc_html_e( 'Slet den gemte nøgle', 'forsamlingshus-billetter' ); ?></label>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fhb-wh"><?php esc_html_e( 'Webhook secret', 'forsamlingshus-billetter' ); ?></label></th>
						<td>
							<input class="regular-text" type="password" id="fhb-wh" name="webhook_secret" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( '' !== $values['webhook_secret'] ? __( 'En hemmelighed er gemt', 'forsamlingshus-billetter' ) : '' ); ?>">
							<?php if ( '' !== $values['webhook_secret'] ) : ?>
								<label><input type="checkbox" name="clear_webhook" value="1"> <?php esc_html_e( 'Slet den gemte hemmelighed', 'forsamlingshus-billetter' ); ?></label>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fhb-webhook-url"><?php esc_html_e( 'Webhook-URL', 'forsamlingshus-billetter' ); ?></label></th>
						<td>
							<input class="large-text" type="text" id="fhb-webhook-url" readonly value="<?php echo esc_attr( $webhook ); ?>" onclick="this.select();">
							<p class="description"><?php esc_html_e( 'I Stripe: Developers → Webhooks → Add endpoint. Vælg checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed og checkout.session.expired.', 'forsamlingshus-billetter' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fhb-mailpoet"><?php esc_html_e( 'MailPoet-mail', 'forsamlingshus-billetter' ); ?></label></th>
						<td>
							<?php $this->mailpoet_field( (int) $values['mailpoet_newsletter_id'] ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fhb-github"><?php esc_html_e( 'GitHub-token', 'forsamlingshus-billetter' ); ?></label></th>
						<td>
							<input class="regular-text" type="password" id="fhb-github" name="github_token" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( '' !== $values['github_token'] ? __( 'Et token er gemt', 'forsamlingshus-billetter' ) : '' ); ?>">
							<?php if ( '' !== $values['github_token'] ) : ?>
								<label><input type="checkbox" name="clear_github" value="1"> <?php esc_html_e( 'Slet det gemte token', 'forsamlingshus-billetter' ); ?></label>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Opdateringer kommer fra GitHub-udgivelser på Jens-ole-nielsen/Billetter. Udgivelsen skal indeholde filen forsamlingshus-billetter.zip. Et offentligt arkiv kan læses uden token. Et privat arkiv kræver et token med læseadgang. Lad feltet stå tomt for at beholde det gemte token.', 'forsamlingshus-billetter' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Gem indstillinger', 'forsamlingshus-billetter' ) ); ?>
			</form>
			<h2><?php esc_html_e( 'Dørvagt', 'forsamlingshus-billetter' ); ?></h2>
			<p><?php esc_html_e( 'Opret en bruger med rollen Dørvagt, hvis personalet ved døren skal kunne tjekke billetter ind uden at kunne ændre arrangementer eller se nøglerne.', 'forsamlingshus-billetter' ); ?></p>
		</div>
		<?php
	}

	public function save(): void {
		if ( ! Capabilities::can( Capabilities::SETTINGS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'fhb_save_settings' );
		$current = $this->settings->all();
		$publishable = isset( $_POST['publishable_key'] ) ? $this->clean_key( wp_unslash( $_POST['publishable_key'] ) ) : '';
		$secret = isset( $_POST['secret_key'] ) ? $this->clean_key( wp_unslash( $_POST['secret_key'] ) ) : '';
		$webhook = isset( $_POST['webhook_secret'] ) ? $this->clean_key( wp_unslash( $_POST['webhook_secret'] ) ) : '';

		if ( '' !== $publishable && ! str_starts_with( $publishable, 'pk_' ) ) {
			Notices::flash( 'error', __( 'Publishable key skal starte med pk_.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		if ( '' !== $secret && ! str_starts_with( $secret, 'sk_' ) ) {
			Notices::flash( 'error', __( 'Secret key skal starte med sk_.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		if ( '' !== $webhook && ! str_starts_with( $webhook, 'whsec_' ) ) {
			Notices::flash( 'error', __( 'Webhook secret skal starte med whsec_.', 'forsamlingshus-billetter' ) );
			$this->back();
		}

		if ( '' === $secret ) {
			$secret = empty( $_POST['clear_secret'] ) ? $current['secret_key'] : '';
		}
		if ( '' === $webhook ) {
			$webhook = empty( $_POST['clear_webhook'] ) ? $current['webhook_secret'] : '';
		}

		$github = isset( $_POST['github_token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['github_token'] ) ) ) : '';
		if ( '' === $github ) {
			$github = empty( $_POST['clear_github'] ) ? $current['github_token'] : '';
		} elseif ( 1 !== preg_match( '/^[A-Za-z0-9_\-]{8,255}$/', $github ) ) {
			Notices::flash( 'error', __( 'GitHub-tokenet blev ikke gemt. Brug et token på 8–255 tegn uden mellemrum.', 'forsamlingshus-billetter' ) );
			$this->back();
		}

		if ( MailPoetBody::available() ) {
			$newsletter_id = isset( $_POST['mailpoet_newsletter_id'] ) ? absint( wp_unslash( $_POST['mailpoet_newsletter_id'] ) ) : 0;
		} else {
			$newsletter_id = (int) $current['mailpoet_newsletter_id'];
		}

		$this->settings->save(
			array(
				'publishable_key' => $publishable,
				'secret_key' => $secret,
				'webhook_secret' => $webhook,
				'github_token' => $github,
				'mailpoet_newsletter_id' => $newsletter_id,
			)
		);
		Notices::flash( 'success', __( 'Indstillingerne er gemt.', 'forsamlingshus-billetter' ) );
		$this->back();
	}

	private function mailpoet_field( int $selected ): void {
		if ( ! MailPoetBody::available() ) {
			echo '<p id="fhb-mailpoet">' . esc_html__( 'MailPoet er ikke aktivt. Billetterne sendes med den indbyggede tekstmail.', 'forsamlingshus-billetter' ) . '</p>';
			return;
		}
		$choices = MailPoetBody::choices();
		echo '<select id="fhb-mailpoet" name="mailpoet_newsletter_id">';
		echo '<option value="0">' . esc_html__( 'Indbygget tekstmail', 'forsamlingshus-billetter' ) . '</option>';
		foreach ( $choices as $id => $label ) {
			echo '<option value="' . esc_attr( (string) $id ) . '" ' . selected( $selected, (int) $id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Den valgte MailPoet-mail er brødteksten i mailen med billetterne. PDF-filen vedhæftes stadig. I mailen kan I bruge [fhb_buyer], [fhb_event_name], [fhb_date], [fhb_time], [fhb_quantity], [fhb_amount], [fhb_order] og [fhb_remark]. [fhb_qr] viser QR-koden for den første billet.', 'forsamlingshus-billetter' ) . '</p>';
	}

	private function clean_key( string $value ): string {
		$value = trim( sanitize_text_field( $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9_\-]{8,255}$/', $value ) ) {
			return '';
		}

		return $value;
	}

	private function back(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=fhb-settings' ) );
		exit;
	}
}
