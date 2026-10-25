<?php
/**
 * Event custom post type and editor.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

use Forsamlingshus\Billetter\Admin\Notices;

defined( 'ABSPATH' ) || exit;

/**
 * An event is a private post. Publishing it creates the public sales page.
 */
final class EventPostType {

	public function __construct(
		private Stock $stock,
		private SalesPage $sales
	) {
	}

	public function hooks(): void {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'save_post_fhb_event', array( $this, 'save' ), 20 );
		add_action( 'wp_trash_post', array( $this, 'trash_sales_page' ) );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'manage_fhb_event_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_fhb_event_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function register(): void {
		register_post_type(
			'fhb_event',
			array(
				'labels' => array(
					'name' => __( 'Arrangementer', 'forsamlingshus-billetter' ),
					'singular_name' => __( 'Arrangement', 'forsamlingshus-billetter' ),
					'add_new' => __( 'Tilføj nyt', 'forsamlingshus-billetter' ),
					'add_new_item' => __( 'Tilføj arrangement', 'forsamlingshus-billetter' ),
					'edit_item' => __( 'Rediger arrangement', 'forsamlingshus-billetter' ),
					'new_item' => __( 'Nyt arrangement', 'forsamlingshus-billetter' ),
					'view_item' => __( 'Se arrangement', 'forsamlingshus-billetter' ),
					'search_items' => __( 'Søg i arrangementer', 'forsamlingshus-billetter' ),
					'not_found' => __( 'Ingen arrangementer endnu.', 'forsamlingshus-billetter' ),
					'not_found_in_trash' => __( 'Ingen arrangementer i papirkurven.', 'forsamlingshus-billetter' ),
					'all_items' => __( 'Arrangementer', 'forsamlingshus-billetter' ),
					'menu_name' => __( 'Arrangementer', 'forsamlingshus-billetter' ),
				),
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => 'fhb-tickets',
				'show_in_rest' => true,
				'menu_icon' => 'dashicons-tickets-alt',
				'capability_type' => array( 'fhb_event', 'fhb_events' ),
				'map_meta_cap' => true,
				'has_archive' => false,
				'rewrite' => false,
				'query_var' => false,
				'exclude_from_search' => true,
				'supports' => array( 'title', 'editor' ),
			)
		);
	}

	public function title_placeholder( string $placeholder, \WP_Post $post ): string {
		if ( 'fhb_event' === $post->post_type ) {
			return __( 'Arrangementets navn', 'forsamlingshus-billetter' );
		}

		return $placeholder;
	}

	public function meta_box(): void {
		add_meta_box(
			'fhb_event_details',
			__( 'Billetsalg', 'forsamlingshus-billetter' ),
			array( $this, 'render_meta_box' ),
			'fhb_event',
			'normal',
			'high'
		);
	}

	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'fhb_save_event', 'fhb_event_nonce' );
		$event = Events::get( (int) $post->ID );
		$capacity = $event ? (int) $event['capacity'] : 0;
		$date = $event ? (string) $event['date'] : '';
		$time = $event ? (string) $event['time'] : '';
		$price = $event && (int) $event['price_ore'] > 0 ? number_format( ( (int) $event['price_ore'] ) / 100, 2, ',', '' ) : '';
		if ( $event && 0 === (int) $event['price_ore'] && '' !== (string) get_post_meta( $post->ID, '_fhb_price_ore', true ) ) {
			$price = '0';
		}
		$invoice = $event && ! empty( $event['invoice'] );
		$poster_id = $event ? (int) $event['poster_id'] : 0;
		$ticket_image_id = $event ? (int) ( $event['ticket_image_id'] ?? 0 ) : 0;
		$ticket_image = $ticket_image_id ? wp_get_attachment_image( $ticket_image_id, 'medium', false, array( 'style' => 'max-width:240px;height:auto;' ) ) : '';
		$page_id = $event ? (int) $event['sales_page_id'] : 0;
		$remaining = $this->stock->remaining( (int) $post->ID );
		$permalink = $page_id > 0 ? get_permalink( $page_id ) : '';
		?>
		<p class="description"><?php esc_html_e( 'Beskrivelsen ovenfor vises på salgssiden. Plakaten er det samme billede, I bruger på Facebook.', 'forsamlingshus-billetter' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="fhb-poster-id"><?php esc_html_e( 'Plakat', 'forsamlingshus-billetter' ); ?></label></th>
				<td>
					<input type="hidden" id="fhb-poster-id" name="fhb_poster_id" value="<?php echo esc_attr( (string) $poster_id ); ?>">
					<div id="fhb-poster-preview">
						<?php
						if ( $poster_id ) {
							echo wp_get_attachment_image( $poster_id, 'medium', false, array( 'style' => 'max-width:240px;height:auto;' ) );
						}
						?>
					</div>
					<p>
						<button type="button" class="button fhb-media-pick" data-target="fhb-poster-id" data-preview="fhb-poster-preview"><?php esc_html_e( 'Vælg plakat', 'forsamlingshus-billetter' ); ?></button>
						<button type="button" class="button fhb-media-clear" data-target="fhb-poster-id" data-preview="fhb-poster-preview"><?php esc_html_e( 'Fjern', 'forsamlingshus-billetter' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Plakaten vises på salgssiden. Uden et billede nedenfor bruges den også på billetten, i stedet for baggrunden fra Billetdesign.', 'forsamlingshus-billetter' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="fhb-ticket-image-id"><?php esc_html_e( 'Billede på billetten', 'forsamlingshus-billetter' ); ?></label></th>
				<td>
					<input type="hidden" id="fhb-ticket-image-id" name="fhb_ticket_image_id" value="<?php echo esc_attr( (string) $ticket_image_id ); ?>">
					<div id="fhb-ticket-image-preview"><?php echo $ticket_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attachment image from WordPress. ?></div>
					<p>
						<button type="button" class="button fhb-media-pick" data-target="fhb-ticket-image-id" data-preview="fhb-ticket-image-preview"><?php esc_html_e( 'Vælg billede', 'forsamlingshus-billetter' ); ?></button>
						<button type="button" class="button fhb-media-clear" data-target="fhb-ticket-image-id" data-preview="fhb-ticket-image-preview"><?php esc_html_e( 'Brug plakaten', 'forsamlingshus-billetter' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Tomt felt bruger plakaten. En plakat eller et billede her erstatter baggrunden fra Billetter → Billetdesign og bruger samme zoom og placering. Tekst, logo og QR-kode bliver, hvor de er sat.', 'forsamlingshus-billetter' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="fhb-capacity"><?php esc_html_e( 'Antal billetter til salg', 'forsamlingshus-billetter' ); ?></label></th>
				<td>
					<input type="number" min="0" step="1" class="small-text" id="fhb-capacity" name="fhb_capacity" value="<?php echo esc_attr( (string) $capacity ); ?>" required>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: tickets still available */
								__( 'Ledige lige nu: %d. Salget stopper, når de er væk.', 'forsamlingshus-billetter' ),
								$remaining
							)
						);
						?>
					</p>
					<label for="fhb-show-remaining">
						<input type="checkbox" id="fhb-show-remaining" name="fhb_show_remaining" value="1" <?php checked( ! $event || ! empty( $event['show_remaining'] ) ); ?>>
						<?php esc_html_e( 'Vis, hvor mange billetter der er tilbage, på salgssiden', 'forsamlingshus-billetter' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Slået til som standard. Slår du den fra, skjules tallet for køberne. Her i administrationen kan I stadig se solgte og ledige.', 'forsamlingshus-billetter' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="fhb-date"><?php esc_html_e( 'Dato', 'forsamlingshus-billetter' ); ?></label></th>
				<td><input type="date" id="fhb-date" name="fhb_date" value="<?php echo esc_attr( $date ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="fhb-time"><?php esc_html_e( 'Tidspunkt', 'forsamlingshus-billetter' ); ?></label></th>
				<td><input type="time" id="fhb-time" name="fhb_time" value="<?php echo esc_attr( $time ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="fhb-price"><?php esc_html_e( 'Pris (DKK)', 'forsamlingshus-billetter' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="fhb-price" name="fhb_price" value="<?php echo esc_attr( $price ); ?>" inputmode="decimal" placeholder="125,00">
					<p class="description"><?php esc_html_e( 'Skriv 0 for et gratis arrangement. Prisen er det beløb, køberen betaler.', 'forsamlingshus-billetter' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Faktura', 'forsamlingshus-billetter' ); ?></th>
				<td>
					<label for="fhb-invoice">
						<input type="checkbox" id="fhb-invoice" name="fhb_invoice" value="1" <?php checked( $invoice ); ?>>
						<?php esc_html_e( 'Betaling via faktura (efter aftale)', 'forsamlingshus-billetter' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Når dette er slået til, kan køberen vælge faktura. Billetterne sendes først, når du markerer ordren som betalt. Ellers er der kun Stripe.', 'forsamlingshus-billetter' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Salgsside', 'forsamlingshus-billetter' ); ?></th>
				<td>
					<?php if ( is_string( $permalink ) && '' !== $permalink ) : ?>
						<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $permalink ); ?></a>
					<?php else : ?>
						<?php esc_html_e( 'Salgssiden oprettes, når arrangementet udgives.', 'forsamlingshus-billetter' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( 'auto-draft' !== $post->post_status ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Nulstil salg', 'forsamlingshus-billetter' ); ?></th>
					<td>
						<?php $this->reset_button( $post ); ?>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: sold tickets, 2: reserved tickets */
									__( 'Solgt lige nu: %1$d. Reserveret: %2$d. Knappen sletter kun ordrer og billetter til dette arrangement. Arrangementet bliver stående, og andre arrangementer røres ikke.', 'forsamlingshus-billetter' ),
									$this->stock->sold( (int) $post->ID ),
									$this->reserved( (int) $post->ID )
								)
							);
							?>
						</p>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	public function save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$nonce = isset( $_POST['fhb_event_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['fhb_event_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'fhb_save_event' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$capacity = isset( $_POST['fhb_capacity'] ) ? absint( $_POST['fhb_capacity'] ) : 0;
		$date = isset( $_POST['fhb_date'] ) ? sanitize_text_field( wp_unslash( $_POST['fhb_date'] ) ) : '';
		$time = isset( $_POST['fhb_time'] ) ? sanitize_text_field( wp_unslash( $_POST['fhb_time'] ) ) : '';
		$price_raw = isset( $_POST['fhb_price'] ) ? sanitize_text_field( wp_unslash( $_POST['fhb_price'] ) ) : '';
		$invoice = empty( $_POST['fhb_invoice'] ) ? '0' : '1';
		$poster_id = isset( $_POST['fhb_poster_id'] ) ? absint( $_POST['fhb_poster_id'] ) : 0;
		if ( $poster_id && ! wp_attachment_is_image( $poster_id ) ) {
			$poster_id = 0;
		}
		$ticket_image_id = isset( $_POST['fhb_ticket_image_id'] ) ? absint( $_POST['fhb_ticket_image_id'] ) : 0;
		if ( $ticket_image_id && ! wp_attachment_is_image( $ticket_image_id ) ) {
			$ticket_image_id = 0;
		}

		if ( '' !== $date ) {
			$date_ok = date_create_immutable( $date );
			if ( ! $date_ok || $date_ok->format( 'Y-m-d' ) !== $date ) {
				$date = '';
				Notices::flash( 'error', __( 'Datoen blev ikke gemt. Brug formatet åååå-mm-dd.', 'forsamlingshus-billetter' ) );
			}
		}
		if ( '' !== $time && 1 !== preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			$time = '';
			Notices::flash( 'error', __( 'Tidspunktet blev ikke gemt.', 'forsamlingshus-billetter' ) );
		}
		if ( '' === $date && 'publish' === get_post_status( $post_id ) ) {
			Notices::flash( 'error', __( 'Angiv en dato, så den kan vises på salgssiden og billetten.', 'forsamlingshus-billetter' ) );
		}

		if ( '' === trim( $price_raw ) ) {
			$price_ore = 0;
		} else {
			$price_ore = Money::parse_ore( $price_raw );
			if ( null === $price_ore ) {
				$price_ore = (int) get_post_meta( $post_id, '_fhb_price_ore', true );
				Notices::flash( 'error', __( 'Prisen blev ikke genkendt. Skriv for eksempel 125 eller 125,50.', 'forsamlingshus-billetter' ) );
			}
		}

		if ( ! $this->stock->sync( $post_id, $capacity ) ) {
			$row = $this->stock->get( $post_id );
			$capacity = $row ? (int) $row->capacity : $capacity;
			Notices::flash( 'error', __( 'Kapaciteten kan ikke sættes lavere end de billetter, der allerede er reserveret eller solgt.', 'forsamlingshus-billetter' ) );
		}

		update_post_meta( $post_id, '_fhb_capacity', $capacity );
		update_post_meta( $post_id, '_fhb_show_remaining', empty( $_POST['fhb_show_remaining'] ) ? '0' : '1' );
		update_post_meta( $post_id, '_fhb_date', $date );
		update_post_meta( $post_id, '_fhb_time', $time );
		update_post_meta( $post_id, '_fhb_price_ore', $price_ore );
		update_post_meta( $post_id, '_fhb_invoice', $invoice );
		update_post_meta( $post_id, '_fhb_poster_id', $poster_id );
		update_post_meta( $post_id, '_fhb_ticket_image_id', $ticket_image_id );
		if ( $poster_id ) {
			set_post_thumbnail( $post_id, $poster_id );
		} else {
			delete_post_thumbnail( $post_id );
		}

		$page_id = $this->sales->sync( $post_id );
		if ( $page_id > 0 && 'publish' === get_post_status( $post_id ) ) {
			$link = get_permalink( $page_id );
			if ( is_string( $link ) && '' !== $link ) {
				Notices::flash( 'success', __( 'Salgssiden er opdateret.', 'forsamlingshus-billetter' ) . ' ' . $link );
			}
		}
	}

	public function trash_sales_page( int $post_id ): void {
		if ( 'fhb_event' !== get_post_type( $post_id ) ) {
			return;
		}
		$page_id = (int) get_post_meta( $post_id, '_fhb_sales_page_id', true );
		if ( $page_id > 0 ) {
			wp_update_post(
				array(
					'ID' => $page_id,
					'post_status' => 'draft',
				)
			);
		}
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$columns['fhb_when'] = __( 'Tidspunkt', 'forsamlingshus-billetter' );
		$columns['fhb_price'] = __( 'Pris', 'forsamlingshus-billetter' );
		$columns['fhb_sold'] = __( 'Solgt', 'forsamlingshus-billetter' );
		$columns['fhb_left'] = __( 'Ledige', 'forsamlingshus-billetter' );
		$columns['fhb_link'] = __( 'Salgsside', 'forsamlingshus-billetter' );

		return $columns;
	}

	public function column( string $column, int $post_id ): void {
		$event = Events::get( $post_id );
		if ( ! $event ) {
			return;
		}
		if ( 'fhb_when' === $column ) {
			echo esc_html( trim( Format::date( (string) $event['date'] ) . ' ' . Format::time( (string) $event['time'] ) ) );
		} elseif ( 'fhb_price' === $column ) {
			echo esc_html( Money::format_ore( (int) $event['price_ore'] ) );
		} elseif ( 'fhb_sold' === $column ) {
			echo esc_html( (string) $this->stock->sold( $post_id ) );
		} elseif ( 'fhb_left' === $column ) {
			echo esc_html( (string) $this->stock->remaining( $post_id ) );
		} elseif ( 'fhb_link' === $column ) {
			$link = (int) $event['sales_page_id'] > 0 ? get_permalink( (int) $event['sales_page_id'] ) : '';
			if ( is_string( $link ) && '' !== $link ) {
				echo '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Åbn', 'forsamlingshus-billetter' ) . '</a>';
			} else {
				echo '—';
			}
		}
	}

	public function assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || 'fhb_event' !== $screen->post_type ) {
			return;
		}
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'fhb-media',
			FHB_URL . 'assets/js/media.js',
			array( 'media-editor' ),
			FHB_VERSION,
			true
		);
		wp_enqueue_script(
			'fhb-event-reset',
			FHB_URL . 'assets/js/event-reset.js',
			array(),
			FHB_VERSION,
			true
		);
	}

	private function reset_button( \WP_Post $post ): void {
		$confirm = __( 'Alle ordrer og billetter til dette arrangement slettes, og det kan ikke fortrydes. Penge, der allerede er trukket i Stripe, bliver ikke refunderet.', 'forsamlingshus-billetter' );
		echo '<button type="button" class="button button-secondary" id="fhb-reset-sales"';
		echo ' data-confirm="' . esc_attr( $confirm ) . '"';
		echo ' data-action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"';
		echo ' data-event="' . esc_attr( (string) $post->ID ) . '"';
		echo ' data-nonce="' . esc_attr( wp_create_nonce( 'fhb_reset_event_' . $post->ID ) ) . '">';
		echo esc_html__( 'Nulstil solgte billetter', 'forsamlingshus-billetter' );
		echo '</button>';
	}

	private function reserved( int $event_id ): int {
		$row = $this->stock->get( $event_id );
		if ( ! $row ) {
			return 0;
		}

		return max( 0, (int) $row->reserved );
	}
}
