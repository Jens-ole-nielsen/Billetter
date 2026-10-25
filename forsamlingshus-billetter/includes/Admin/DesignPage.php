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
use Forsamlingshus\Billetter\TicketFrame;

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
		$background = (int) $design['background_id'] ? wp_get_attachment_image_url( (int) $design['background_id'], 'large' ) : '';
		$has_photo = is_string( $background ) && '' !== $background;
		$pads = TicketFrame::pads_content( $design['pad_blocks'] ?? 'auto', $has_photo );
		$size = TicketFrame::size( (string) $design['ticket_size'] );
		?>
		<div class="wrap fhb-design-wrap">
			<h1><?php esc_html_e( 'Billetdesign', 'forsamlingshus-billetter' ); ?></h1>
			<p><?php esc_html_e( 'Vælg baggrund, størrelse, logo og hvilke felter der skal med. Træk felterne på eksemplet. Gem design, så PDF-billetten bruger de samme placeringer.', 'forsamlingshus-billetter' ); ?></p>
			<p class="description"><?php esc_html_e( 'Har arrangementet en plakat eller sit eget billetbillede, bruges det i stedet for baggrunden her. Størrelse og placering følger stadig dette design. Uden billede er billetten ensfarvet. QR-koden står altid på en hvid flade, så den kan scannes.', 'forsamlingshus-billetter' ); ?></p>
			<div class="fhb-design-layout">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'fhb_save_design' ); ?>
					<input type="hidden" name="action" value="fhb_save_design">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="fhb-elementor-template"><?php esc_html_e( 'Elementor-skabelon', 'forsamlingshus-billetter' ); ?></label></th>
							<td><?php $this->elementor_field( (int) $design['elementor_template_id'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="fhb-ticket-size"><?php esc_html_e( 'Billetstørrelse', 'forsamlingshus-billetter' ); ?></label></th>
							<td>
								<select id="fhb-ticket-size" name="ticket_size">
									<?php foreach ( TicketFrame::sizes() as $key => $choice ) : ?>
										<option
											value="<?php echo esc_attr( $key ); ?>"
											data-ratio="<?php echo esc_attr( $this->css_num( $choice['w'] ) . ' / ' . $this->css_num( $choice['h'] ) ); ?>"
											data-label="<?php echo esc_attr( TicketFrame::fit_label( $key ) ); ?>"
											<?php selected( $size['key'], $key ); ?>
										><?php echo esc_html( $choice['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<p id="fhb-size-hint" class="description">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: pixel size and orientation, for example 1240 × 1754 pixels, stående */
											__( 'Billedet skal være %s.', 'forsamlingshus-billetter' ),
											TicketFrame::fit_label( $size['key'] )
										)
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Baggrundsbillede', 'forsamlingshus-billetter' ); ?></th>
							<td>
								<input type="hidden" id="fhb-background-id" name="background_id" value="<?php echo esc_attr( (string) $design['background_id'] ); ?>">
								<div id="fhb-background-preview">
									<?php if ( is_string( $background ) && '' !== $background ) : ?>
										<img src="<?php echo esc_url( $background ); ?>" alt="" style="max-width:240px;height:auto;">
									<?php endif; ?>
								</div>
								<button type="button" class="button fhb-media-pick" data-target="fhb-background-id" data-preview="fhb-background-preview"><?php esc_html_e( 'Vælg billede', 'forsamlingshus-billetter' ); ?></button>
								<button type="button" class="button fhb-media-clear" data-target="fhb-background-id" data-preview="fhb-background-preview"><?php esc_html_e( 'Fjern', 'forsamlingshus-billetter' ); ?></button>
								<p class="description"><?php esc_html_e( 'Bruges når arrangementet ikke har en plakat eller et eget billetbillede. Billedet skaleres og beskæres, så det fylder billetten.', 'forsamlingshus-billetter' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="fhb-pad-blocks"><?php esc_html_e( 'Lys flade bag tekst og logo', 'forsamlingshus-billetter' ); ?></label></th>
							<td>
								<select id="fhb-pad-blocks" name="pad_blocks">
									<option value="auto" <?php selected( (string) $design['pad_blocks'], 'auto' ); ?>><?php esc_html_e( 'Når der er et billede', 'forsamlingshus-billetter' ); ?></option>
									<option value="1" <?php selected( (string) $design['pad_blocks'], '1' ); ?>><?php esc_html_e( 'Altid', 'forsamlingshus-billetter' ); ?></option>
									<option value="0" <?php selected( (string) $design['pad_blocks'], '0' ); ?>><?php esc_html_e( 'Nej', 'forsamlingshus-billetter' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Tekst og logo kan stå på en hvid flade, så de kan læses oven på et billede. Som standard er fladen slået til, når billetten har et billede. QR-koden har altid hvid baggrund, og det kan ikke slås fra.', 'forsamlingshus-billetter' ); ?></p>
							</td>
						</tr>
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
								<p class="description"><?php esc_html_e( 'Koden til manuel indtastning står altid på billetten, også hvis QR-koden er slået fra. Den følger QR-feltet. Sammen med QR-koden er teksten sort på den hvide flade. Uden QR-koden er den almindelig tekst i samme farve som de andre felter.', 'forsamlingshus-billetter' ); ?></p>
							</td>
						</tr>
					</table>
					<?php $this->position_inputs( $design ); ?>
					<?php submit_button( __( 'Gem design', 'forsamlingshus-billetter' ) ); ?>
				</form>
				<div>
					<h2><?php esc_html_e( 'Eksempel', 'forsamlingshus-billetter' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Træk et felt, logoet eller QR-koden. Slip, og gem design.', 'forsamlingshus-billetter' ); ?></p>
					<article id="fhb-ticket-preview" class="fhb-ticket-preview<?php echo $has_photo ? ' has-photo' : ''; ?><?php echo $pads ? ' has-pad' : ''; ?>" style="<?php echo esc_attr( $this->preview_style( $design, is_string( $background ) ? $background : '' ) ); ?>">
						<?php $this->preview_blocks( $design, is_string( $logo ) ? $logo : '' ); ?>
					</article>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string, int|string> $design
	 */
	private function position_inputs( array $design ): void {
		foreach ( TicketFrame::default_blocks() as $name => $pos ) {
			$x = (int) ( $design[ 'block_' . $name . '_x' ] ?? $pos['x'] );
			$y = (int) ( $design[ 'block_' . $name . '_y' ] ?? $pos['y'] );
			echo '<input type="hidden" name="block_' . esc_attr( $name ) . '_x" id="fhb-block-' . esc_attr( $name ) . '-x" value="' . esc_attr( (string) $x ) . '">';
			echo '<input type="hidden" name="block_' . esc_attr( $name ) . '_y" id="fhb-block-' . esc_attr( $name ) . '-y" value="' . esc_attr( (string) $y ) . '">';
		}
	}

	/**
	 * @param array<string, int|string> $design
	 */
	private function preview_blocks( array $design, string $logo ): void {
		$blocks = array(
			'heading' => '',
			'event' => 'show_event',
			'date' => 'show_date',
			'time' => 'show_time',
			'buyer' => 'show_buyer',
			'price' => 'show_price',
		);
		echo '<strong class="fhb-block fhb-ticket-heading" data-block="heading" data-fhb-preview="heading" style="' . esc_attr( $this->block_style( $design, 'heading' ) ) . '">' . esc_html( (string) $design['heading'] ) . '</strong>';
		$rows = array(
			'event' => array( __( 'Arrangement', 'forsamlingshus-billetter' ), __( 'Fest i forsamlingshuset', 'forsamlingshus-billetter' ) ),
			'date' => array( __( 'Dato', 'forsamlingshus-billetter' ), __( 'lørdag 14. juni 2026', 'forsamlingshus-billetter' ) ),
			'time' => array( __( 'Tidspunkt', 'forsamlingshus-billetter' ), '19:00' ),
			'buyer' => array( __( 'Køber', 'forsamlingshus-billetter' ), __( 'Jens Hansen', 'forsamlingshus-billetter' ) ),
			'price' => array( __( 'Pris', 'forsamlingshus-billetter' ), '150,00 kr.' ),
		);
		foreach ( $rows as $name => $pair ) {
			$hidden = empty( $design[ $blocks[ $name ] ] ) ? ' hidden' : '';
			echo '<p class="fhb-block" data-block="' . esc_attr( $name ) . '" data-fhb-row="' . esc_attr( $blocks[ $name ] ) . '" style="' . esc_attr( $this->block_style( $design, $name ) ) . '"' . $hidden . '>';
			echo '<span>' . esc_html( $pair[0] ) . '</span><strong>' . esc_html( $pair[1] ) . '</strong></p>';
		}
		$qr_on = ! empty( $design['show_qr'] );
		$qr_hidden = $qr_on ? '' : ' hidden';
		echo '<div class="fhb-block fhb-block-qr' . ( $qr_on ? '' : ' is-plain' ) . '" data-block="qr" style="' . esc_attr( $this->block_style( $design, 'qr' ) ) . '">';
		echo '<div data-fhb-row="show_qr" class="fhb-ticket-qr"' . $qr_hidden . '>' . $this->sample_qr() . '</div>';
		echo '<p class="fhb-ticket-code">' . esc_html__( 'Billet 1 af 2', 'forsamlingshus-billetter' ) . '<br><code>0123 4567 89ab cdef</code><br><code>0123 4567 89ab cdef</code><br>' . esc_html__( 'Vis koden ved indgangen', 'forsamlingshus-billetter' ) . '</p>';
		echo '</div>';
		$logo_attr = '' !== $logo ? ' src="' . esc_url( $logo ) . '"' : ' hidden';
		echo '<img class="fhb-block fhb-ticket-logo" data-block="logo" data-fhb-preview="logo" alt="" draggable="false" style="' . esc_attr( $this->block_style( $design, 'logo' ) ) . '"' . $logo_attr . '>';
	}

	/**
	 * @param array<string, int|string> $design
	 */
	private function block_style( array $design, string $name ): string {
		$pos = TicketFrame::default_blocks()[ $name ];
		$x = (int) ( $design[ 'block_' . $name . '_x' ] ?? $pos['x'] );
		$y = (int) ( $design[ 'block_' . $name . '_y' ] ?? $pos['y'] );

		return 'left:' . $this->css_num( $x / 10 ) . '%;top:' . $this->css_num( $y / 10 ) . '%;';
	}

	private function css_num( float $number ): string {
		return rtrim( rtrim( sprintf( '%.2F', $number ), '0' ), '.' );
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
	private function preview_style( array $design, string $background ): string {
		$size = TicketFrame::size( (string) $design['ticket_size'] );
		$style = '--fhb-primary:' . (string) $design['color_primary']
			. ';--fhb-text:' . (string) $design['color_text']
			. ';--fhb-bg:' . (string) $design['color_background']
			. ';aspect-ratio:' . $this->css_num( $size['w'] ) . ' / ' . $this->css_num( $size['h'] ) . ';';
		if ( '' !== $background ) {
			$style .= "background-image:url('" . str_replace( array( "'", '(', ')' ), '', $background ) . "');";
		}

		return $style;
	}

	private function elementor_field( int $selected ): void {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			echo '<input type="hidden" name="elementor_template_id" value="' . esc_attr( (string) $selected ) . '">';
			echo '<p id="fhb-elementor-template">' . esc_html__( 'Elementor er ikke aktivt, så det indbyggede design bruges.', 'forsamlingshus-billetter' ) . '</p>';
			return;
		}

		$posts = get_posts(
			array(
				'post_type' => 'elementor_library',
				'post_status' => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => 100,
				'orderby' => 'title',
				'order' => 'ASC',
			)
		);
		echo '<select id="fhb-elementor-template" name="elementor_template_id">';
		echo '<option value="0">' . esc_html__( 'Indbygget design', 'forsamlingshus-billetter' ) . '</option>';
		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}
				echo '<option value="' . esc_attr( (string) $post->ID ) . '" ' . selected( $selected, (int) $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>';
			}
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Vælg en gemt Elementor-skabelon, hvis PDF-billetten skal bruge den. Indbygget design bruger felterne nedenfor. I skabelonen kan I bruge kortkoderne [fhb_event_name], [fhb_date], [fhb_time], [fhb_buyer], [fhb_price], [fhb_remark] og [fhb_qr], eller de dynamiske tags i gruppen Billet. Hold skabelonen enkel. Et kompliceret layout bliver ikke altid ens i PDF-filen.', 'forsamlingshus-billetter' ) . '</p>';
	}

	private function sample_qr(): string {
		$svg = QrCode::svg_markup( 'fhb-sample-preview' );
		if ( '' === $svg ) {
			return '';
		}

		return $svg;
	}
}
