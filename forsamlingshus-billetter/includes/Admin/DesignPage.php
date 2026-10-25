<?php
/**
 * Ticket designer.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Admin;

use Forsamlingshus\Billetter\Capabilities;
use Forsamlingshus\Billetter\Design;
use Forsamlingshus\Billetter\QrCode;

defined( 'ABSPATH' ) || exit;

/**
 * Logo, heading, colors and the fields that are printed. The preview updates in the browser.
 */
final class DesignPage {

	public function __construct( private Design $design ) {
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_fhb_save_design', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'fhb-tickets',
			__( 'Billetdesign', 'forsamlingshus-billetter' ),
			__( 'Billetdesign', 'forsamlingshus-billetter' ),
			Capabilities::DESIGN,
			'fhb-design',
			array( $this, 'render' )
		);
	}

	public function assets( string $hook ): void {
		if ( 'billetter_page_fhb-design' !== $hook && false === strpos( $hook, 'fhb-design' ) ) {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			if ( 'fhb-design' !== $page ) {
				return;
			}
		}
		wp_enqueue_media();
		wp_enqueue_style( 'fhb-admin', FHB_URL . 'assets/css/admin.css', array(), FHB_VERSION );
		wp_enqueue_script( 'fhb-media', FHB_URL . 'assets/js/media.js', array( 'media-editor' ), FHB_VERSION, true );
		wp_enqueue_script( 'fhb-design', FHB_URL . 'assets/js/design.js', array(), FHB_VERSION, true );
	}

	public function render(): void {
		if ( ! Capabilities::can( Capabilities::DESIGN ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		$design = $this->design->get();
		$logo = (int) $design['logo_id'] ? wp_get_attachment_image_url( (int) $design['logo_id'], 'medium' ) : '';
		?>
		<div class="wrap fhb-design-wrap">
			<h1><?php esc_html_e( 'Billetdesign', 'forsamlingshus-billetter' ); ?></h1>
			<p><?php esc_html_e( 'Vælg logo, overskrift, farver og hvilke felter der skal med på billetten. Forhåndsvisningen er et eksempel og følger dine valg.', 'forsamlingshus-billetter' ); ?></p>
			<div class="fhb-design-layout">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'fhb_save_design' ); ?>
					<input type="hidden" name="action" value="fhb_save_design">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Logo', 'forsamlingshus-billetter' ); ?></th>
							<td>
								<input type="hidden" id="fhb-logo-id" name="logo_id" value="<?php echo esc_attr( (string) $design['logo_id'] ); ?>">
								<div id="fhb-logo-preview">
									<?php if ( is_string( $logo ) && '' !== $logo ) : ?>
										<img src="<?php echo esc_url( $logo ); ?>" alt="" style="max-width:160px;height:auto;">
									<?php endif; ?>
								</div>
								<button type="button" class="button fhb-media-pick" data-target="fhb-logo-id" data-preview="fhb-logo-preview"><?php esc_html_e( 'Vælg logo', 'forsamlingshus-billetter' ); ?></button>
								<button type="button" class="button fhb-media-clear" data-target="fhb-logo-id" data-preview="fhb-logo-preview"><?php esc_html_e( 'Fjern', 'forsamlingshus-billetter' ); ?></button>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="fhb-heading"><?php esc_html_e( 'Overskrift', 'forsamlingshus-billetter' ); ?></label></th>
							<td><input class="regular-text" type="text" id="fhb-heading" name="heading" maxlength="80" value="<?php echo esc_attr( (string) $design['heading'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Farver', 'forsamlingshus-billetter' ); ?></th>
							<td>
								<label><?php esc_html_e( 'Primær', 'forsamlingshus-billetter' ); ?> <input type="color" name="color_primary" value="<?php echo esc_attr( (string) $design['color_primary'] ); ?>" data-fhb-color="primary"></label>
								<label><?php esc_html_e( 'Tekst', 'forsamlingshus-billetter' ); ?> <input type="color" name="color_text" value="<?php echo esc_attr( (string) $design['color_text'] ); ?>" data-fhb-color="text"></label>
								<label><?php esc_html_e( 'Baggrund', 'forsamlingshus-billetter' ); ?> <input type="color" name="color_background" value="<?php echo esc_attr( (string) $design['color_background'] ); ?>" data-fhb-color="background"></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Felter på billetten', 'forsamlingshus-billetter' ); ?></th>
							<td>
								<?php
								$fields = array(
									'show_event' => __( 'Arrangementets navn', 'forsamlingshus-billetter' ),
									'show_date' => __( 'Dato', 'forsamlingshus-billetter' ),
									'show_time' => __( 'Tidspunkt', 'forsamlingshus-billetter' ),
									'show_buyer' => __( 'Køber', 'forsamlingshus-billetter' ),
									'show_price' => __( 'Pris', 'forsamlingshus-billetter' ),
									'show_qr' => __( 'QR-kode', 'forsamlingshus-billetter' ),
								);
								foreach ( $fields as $name => $label ) :
									?>
									<label class="fhb-check">
										<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" data-fhb-field="<?php echo esc_attr( $name ); ?>" <?php checked( ! empty( $design[ $name ] ) ); ?>>
										<?php echo esc_html( $label ); ?>
									</label><br>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Koden til manuel indtastning står altid på billetten, også hvis QR-koden er slået fra.', 'forsamlingshus-billetter' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Gem design', 'forsamlingshus-billetter' ) ); ?>
				</form>
				<div>
					<h2><?php esc_html_e( 'Eksempel', 'forsamlingshus-billetter' ); ?></h2>
					<article id="fhb-ticket-preview" class="fhb-ticket-preview" style="<?php echo esc_attr( $this->preview_style( $design ) ); ?>">
						<header class="fhb-ticket-banner">
							<strong data-fhb-preview="heading"><?php echo esc_html( (string) $design['heading'] ); ?></strong>
							<img data-fhb-preview="logo" alt="" <?php echo is_string( $logo ) && '' !== $logo ? 'src="' . esc_url( $logo ) . '"' : 'hidden'; ?>>
						</header>
						<div class="fhb-ticket-body">
							<p data-fhb-row="show_event"><span><?php esc_html_e( 'Arrangement', 'forsamlingshus-billetter' ); ?></span><strong><?php esc_html_e( 'Fest i forsamlingshuset', 'forsamlingshus-billetter' ); ?></strong></p>
							<p data-fhb-row="show_date"><span><?php esc_html_e( 'Dato', 'forsamlingshus-billetter' ); ?></span><strong><?php esc_html_e( 'lørdag 14. juni 2026', 'forsamlingshus-billetter' ); ?></strong></p>
							<p data-fhb-row="show_time"><span><?php esc_html_e( 'Tidspunkt', 'forsamlingshus-billetter' ); ?></span><strong>19:00</strong></p>
							<p data-fhb-row="show_buyer"><span><?php esc_html_e( 'Køber', 'forsamlingshus-billetter' ); ?></span><strong><?php esc_html_e( 'Jens Hansen', 'forsamlingshus-billetter' ); ?></strong></p>
							<p data-fhb-row="show_price"><span><?php esc_html_e( 'Pris', 'forsamlingshus-billetter' ); ?></span><strong>150,00 kr.</strong></p>
							<div data-fhb-row="show_qr" class="fhb-ticket-qr"><?php echo $this->sample_qr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from our token, script tags rejected. ?></div>
							<p class="fhb-ticket-code"><?php esc_html_e( 'Billet 1 af 2', 'forsamlingshus-billetter' ); ?><br><code>0123 4567 89ab cdef</code></p>
						</div>
					</article>
				</div>
			</div>
		</div>
		<?php
	}

	public function save(): void {
		if ( ! Capabilities::can( Capabilities::DESIGN ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'fhb_save_design' );
		$this->design->save( wp_unslash( $_POST ) );
		Notices::flash( 'success', __( 'Billetdesignet er gemt.', 'forsamlingshus-billetter' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=fhb-design' ) );
		exit;
	}

	/**
	 * @param array<string, int|string> $design
	 */
	private function preview_style( array $design ): string {
		return '--fhb-primary:' . (string) $design['color_primary'] . ';--fhb-text:' . (string) $design['color_text'] . ';--fhb-bg:' . (string) $design['color_background'] . ';';
	}

	private function sample_qr(): string {
		$svg = QrCode::svg_markup( 'fhb-sample-preview' );
		if ( '' === $svg ) {
			return '';
		}

		return $svg;
	}
}
