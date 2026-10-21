<?php
/**
 * Plugin settings page.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Settings
 */
class EB_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Add settings submenu under Events.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=eb_event',
			__( 'Eventbilletter-indstillinger', 'eventbilletter' ),
			__( 'Indstillinger', 'eventbilletter' ),
			'manage_options',
			'eventbilletter-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register() {
		register_setting(
			'eb_settings_group',
			'eb_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$existing = get_option( 'eb_settings', array() );
		if ( ! is_array( $input ) ) {
			return $existing;
		}

		$out = $existing;

		$out['stripe_mode']             = ( isset( $input['stripe_mode'] ) && 'live' === $input['stripe_mode'] ) ? 'live' : 'test';
		$out['stripe_test_secret']      = isset( $input['stripe_test_secret'] ) ? sanitize_text_field( $input['stripe_test_secret'] ) : '';
		$out['stripe_test_publishable'] = isset( $input['stripe_test_publishable'] ) ? sanitize_text_field( $input['stripe_test_publishable'] ) : '';
		$out['stripe_live_secret']      = isset( $input['stripe_live_secret'] ) ? sanitize_text_field( $input['stripe_live_secret'] ) : '';
		$out['stripe_live_publishable'] = isset( $input['stripe_live_publishable'] ) ? sanitize_text_field( $input['stripe_live_publishable'] ) : '';
		$out['stripe_webhook_secret']   = isset( $input['stripe_webhook_secret'] ) ? sanitize_text_field( $input['stripe_webhook_secret'] ) : '';
		$out['currency']                = isset( $input['currency'] ) ? strtolower( sanitize_text_field( $input['currency'] ) ) : 'dkk';
		$out['from_email']              = isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : get_option( 'admin_email' );
		$out['from_name']               = isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : get_bloginfo( 'name' );
		$out['success_page_id']         = isset( $input['success_page_id'] ) ? absint( $input['success_page_id'] ) : 0;
		$out['cancel_page_id']          = isset( $input['cancel_page_id'] ) ? absint( $input['cancel_page_id'] ) : 0;

		return $out;
	}

	/**
	 * Render settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s           = get_option( 'eb_settings', array() );
		$webhook_url = rest_url( 'eventbilletter/v1/stripe-webhook' );
		?>
		<div class="wrap eb-settings">
			<h1><?php esc_html_e( 'Eventbilletter — indstillinger', 'eventbilletter' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'eb_settings_group' ); ?>

				<h2><?php esc_html_e( 'Stripe', 'eventbilletter' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Opret en Stripe-konto og indsæt dine API-nøgler. Brug testnøgler indtil du er klar til live salg.', 'eventbilletter' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Tilstand', 'eventbilletter' ); ?></th>
						<td>
							<label>
								<input type="radio" name="eb_settings[stripe_mode]" value="test" <?php checked( $s['stripe_mode'] ?? 'test', 'test' ); ?> />
								<?php esc_html_e( 'Test', 'eventbilletter' ); ?>
							</label>
							&nbsp;
							<label>
								<input type="radio" name="eb_settings[stripe_mode]" value="live" <?php checked( $s['stripe_mode'] ?? '', 'live' ); ?> />
								<?php esc_html_e( 'Live', 'eventbilletter' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test secret key', 'eventbilletter' ); ?></th>
						<td><input type="password" class="regular-text" name="eb_settings[stripe_test_secret]" value="<?php echo esc_attr( $s['stripe_test_secret'] ?? '' ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test publishable key', 'eventbilletter' ); ?></th>
						<td><input type="text" class="regular-text" name="eb_settings[stripe_test_publishable]" value="<?php echo esc_attr( $s['stripe_test_publishable'] ?? '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Live secret key', 'eventbilletter' ); ?></th>
						<td><input type="password" class="regular-text" name="eb_settings[stripe_live_secret]" value="<?php echo esc_attr( $s['stripe_live_secret'] ?? '' ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Live publishable key', 'eventbilletter' ); ?></th>
						<td><input type="text" class="regular-text" name="eb_settings[stripe_live_publishable]" value="<?php echo esc_attr( $s['stripe_live_publishable'] ?? '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook signing secret', 'eventbilletter' ); ?></th>
						<td>
							<input type="password" class="regular-text" name="eb_settings[stripe_webhook_secret]" value="<?php echo esc_attr( $s['stripe_webhook_secret'] ?? '' ); ?>" autocomplete="off" />
							<p class="description">
								<?php esc_html_e( 'Opret en webhook i Stripe Dashboard der peger på:', 'eventbilletter' ); ?>
								<code><?php echo esc_html( $webhook_url ); ?></code><br />
								<?php esc_html_e( 'Events: checkout.session.completed, checkout.session.expired, charge.refunded', 'eventbilletter' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Generelt', 'eventbilletter' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Valuta', 'eventbilletter' ); ?></th>
						<td>
							<input type="text" class="small-text" name="eb_settings[currency]" value="<?php echo esc_attr( $s['currency'] ?? 'dkk' ); ?>" maxlength="3" />
							<p class="description"><?php esc_html_e( 'ISO-kode, fx dkk, eur, sek.', 'eventbilletter' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Afsender-navn', 'eventbilletter' ); ?></th>
						<td><input type="text" class="regular-text" name="eb_settings[from_name]" value="<?php echo esc_attr( $s['from_name'] ?? '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Afsender-e-mail', 'eventbilletter' ); ?></th>
						<td><input type="email" class="regular-text" name="eb_settings[from_email]" value="<?php echo esc_attr( $s['from_email'] ?? '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Succes-side', 'eventbilletter' ); ?></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'eb_settings[success_page_id]',
									'show_option_none'  => __( '— Event-siden (standard) —', 'eventbilletter' ),
									'option_none_value' => '0',
									'selected'          => (int) ( $s['success_page_id'] ?? 0 ),
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Annullerings-side', 'eventbilletter' ); ?></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'eb_settings[cancel_page_id]',
									'show_option_none'  => __( '— Event-siden (standard) —', 'eventbilletter' ),
									'option_none_value' => '0',
									'selected'          => (int) ( $s['cancel_page_id'] ?? 0 ),
								)
							);
							?>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Gem indstillinger', 'eventbilletter' ) ); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Shortcodes', 'eventbilletter' ); ?></h2>
			<ul>
				<li><code>[eb_tickets]</code> — <?php esc_html_e( 'Billetformular på event-siden (bruger aktuelt event).', 'eventbilletter' ); ?></li>
				<li><code>[eb_tickets id="123"]</code> — <?php esc_html_e( 'Billetformular for et specifikt event.', 'eventbilletter' ); ?></li>
				<li><code>[eb_checkout_status]</code> — <?php esc_html_e( 'Vis status efter betaling (på succes-/annulleringsside).', 'eventbilletter' ); ?></li>
			</ul>
		</div>
		<?php
	}
}
