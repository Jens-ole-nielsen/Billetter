<?php
/**
 * Event meta boxes: details + ticket types.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Meta_Boxes
 */
class EB_Meta_Boxes {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post_eb_event', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register meta boxes.
	 */
	public function register() {
		add_meta_box(
			'eb_event_details',
			__( 'Event-detaljer', 'eventbilletter' ),
			array( $this, 'render_details' ),
			'eb_event',
			'side',
			'high'
		);

		add_meta_box(
			'eb_ticket_types',
			__( 'Billettyper', 'eventbilletter' ),
			array( $this, 'render_tickets' ),
			'eb_event',
			'normal',
			'high'
		);

		add_meta_box(
			'eb_event_shortcode',
			__( 'Shortcode', 'eventbilletter' ),
			array( $this, 'render_shortcode' ),
			'eb_event',
			'side',
			'default'
		);
	}

	/**
	 * Event details box.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_details( $post ) {
		wp_nonce_field( 'eb_save_event', 'eb_event_nonce' );

		$date       = get_post_meta( $post->ID, '_eb_event_date', true );
		$time       = get_post_meta( $post->ID, '_eb_event_time', true );
		$venue      = get_post_meta( $post->ID, '_eb_event_venue', true );
		$sales_open = get_post_meta( $post->ID, '_eb_sales_open', true );
		if ( '' === $sales_open ) {
			$sales_open = '1';
		}
		?>
		<p>
			<label for="eb_event_date"><strong><?php esc_html_e( 'Dato', 'eventbilletter' ); ?></strong></label><br />
			<input type="date" id="eb_event_date" name="eb_event_date" value="<?php echo esc_attr( $date ); ?>" class="widefat" />
		</p>
		<p>
			<label for="eb_event_time"><strong><?php esc_html_e( 'Tidspunkt', 'eventbilletter' ); ?></strong></label><br />
			<input type="time" id="eb_event_time" name="eb_event_time" value="<?php echo esc_attr( $time ); ?>" class="widefat" />
		</p>
		<p>
			<label for="eb_event_venue"><strong><?php esc_html_e( 'Sted', 'eventbilletter' ); ?></strong></label><br />
			<input type="text" id="eb_event_venue" name="eb_event_venue" value="<?php echo esc_attr( $venue ); ?>" class="widefat" />
		</p>
		<p>
			<label>
				<input type="checkbox" name="eb_sales_open" value="1" <?php checked( $sales_open, '1' ); ?> />
				<?php esc_html_e( 'Billetsalg åbent', 'eventbilletter' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Ticket types box.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_tickets( $post ) {
		$types    = EB_Tickets::get_types( $post->ID );
		$currency = strtoupper( eventbilletter()->get_setting( 'currency', 'dkk' ) );
		?>
		<div class="eb-ticket-types" data-currency="<?php echo esc_attr( $currency ); ?>">
			<table class="widefat eb-ticket-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Navn', 'eventbilletter' ); ?></th>
						<th><?php esc_html_e( 'Beskrivelse', 'eventbilletter' ); ?></th>
						<th><?php echo esc_html( sprintf( /* translators: %s: currency */ __( 'Pris (%s)', 'eventbilletter' ), $currency ) ); ?></th>
						<th><?php esc_html_e( 'Kapacitet', 'eventbilletter' ); ?></th>
						<th><?php esc_html_e( 'Solgt', 'eventbilletter' ); ?></th>
						<th><?php esc_html_e( 'Aktiv', 'eventbilletter' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody class="eb-ticket-rows">
					<?php if ( empty( $types ) ) : ?>
						<?php $this->render_ticket_row( 0, array() ); ?>
					<?php else : ?>
						<?php foreach ( $types as $i => $type ) : ?>
							<?php $this->render_ticket_row( $i, $type ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button eb-add-ticket"><?php esc_html_e( 'Tilføj billet-type', 'eventbilletter' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Kapacitet 0 = ubegrænset. Solgt opdateres automatisk ved køb.', 'eventbilletter' ); ?></p>
		</div>

		<script type="text/html" id="tmpl-eb-ticket-row">
			<?php $this->render_ticket_row( '{{INDEX}}', array(), true ); ?>
		</script>
		<?php
	}

	/**
	 * Single ticket row.
	 *
	 * @param int|string $index Index.
	 * @param array      $type  Type data.
	 * @param bool       $tpl   Is template.
	 */
	private function render_ticket_row( $index, array $type, $tpl = false ) {
		$id          = $type['id'] ?? '';
		$name        = $type['name'] ?? '';
		$description = $type['description'] ?? '';
		$price       = isset( $type['price'] ) ? $type['price'] : '';
		$capacity    = isset( $type['capacity'] ) ? $type['capacity'] : 0;
		$sold        = isset( $type['sold'] ) ? (int) $type['sold'] : 0;
		$active      = $tpl ? true : ( ! isset( $type['active'] ) || $type['active'] );
		?>
		<tr class="eb-ticket-row">
			<td>
				<input type="hidden" name="eb_tickets[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>" />
				<input type="text" name="eb_tickets[<?php echo esc_attr( (string) $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'Standard', 'eventbilletter' ); ?>" class="widefat" />
			</td>
			<td>
				<input type="text" name="eb_tickets[<?php echo esc_attr( (string) $index ); ?>][description]" value="<?php echo esc_attr( $description ); ?>" class="widefat" />
			</td>
			<td>
				<input type="number" step="0.01" min="0" name="eb_tickets[<?php echo esc_attr( (string) $index ); ?>][price]" value="<?php echo esc_attr( (string) $price ); ?>" class="small-text" />
			</td>
			<td>
				<input type="number" step="1" min="0" name="eb_tickets[<?php echo esc_attr( (string) $index ); ?>][capacity]" value="<?php echo esc_attr( (string) $capacity ); ?>" class="small-text" />
			</td>
			<td><span class="eb-sold-count"><?php echo esc_html( (string) $sold ); ?></span></td>
			<td>
				<input type="checkbox" name="eb_tickets[<?php echo esc_attr( (string) $index ); ?>][active]" value="1" <?php checked( $active ); ?> />
			</td>
			<td>
				<button type="button" class="button-link-delete eb-remove-ticket" aria-label="<?php esc_attr_e( 'Fjern', 'eventbilletter' ); ?>">&times;</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Shortcode helper box.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_shortcode( $post ) {
		$sc = '[eb_tickets id="' . (int) $post->ID . '"]';
		printf(
			'<p>%s</p><code style="display:block;padding:8px;background:#f6f7f7;">%s</code><p class="description">%s</p>',
			esc_html__( 'Indsæt denne shortcode på en side, eller tilføj [eb_tickets] i event-indholdet:', 'eventbilletter' ),
			esc_html( $sc ),
			esc_html__( 'På selve event-siden kan du bruge [eb_tickets] uden id.', 'eventbilletter' )
		);
	}

	/**
	 * Save event meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['eb_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eb_event_nonce'] ) ), 'eb_save_event' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$date  = isset( $_POST['eb_event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['eb_event_date'] ) ) : '';
		$time  = isset( $_POST['eb_event_time'] ) ? sanitize_text_field( wp_unslash( $_POST['eb_event_time'] ) ) : '';
		$venue = isset( $_POST['eb_event_venue'] ) ? sanitize_text_field( wp_unslash( $_POST['eb_event_venue'] ) ) : '';
		$open  = ! empty( $_POST['eb_sales_open'] ) ? '1' : '0';

		update_post_meta( $post_id, '_eb_event_date', $date );
		update_post_meta( $post_id, '_eb_event_time', $time );
		update_post_meta( $post_id, '_eb_event_venue', $venue );
		update_post_meta( $post_id, '_eb_sales_open', $open );

		$tickets = isset( $_POST['eb_tickets'] ) && is_array( $_POST['eb_tickets'] ) ? wp_unslash( $_POST['eb_tickets'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		EB_Tickets::save_types( $post_id, $tickets );
	}
}
