<?php
/**
 * Orders list columns and detail view.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Orders_Admin
 */
class EB_Orders_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'manage_eb_order_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_eb_order_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_action( 'add_meta_boxes_eb_order', array( $this, 'meta_boxes' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
	}

	/**
	 * Custom columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		return array(
			'cb'       => $columns['cb'] ?? '',
			'title'    => __( 'Ordre', 'eventbilletter' ),
			'status'   => __( 'Status', 'eventbilletter' ),
			'event'    => __( 'Event', 'eventbilletter' ),
			'customer' => __( 'Kunde', 'eventbilletter' ),
			'total'    => __( 'Total', 'eventbilletter' ),
			'date'     => __( 'Dato', 'eventbilletter' ),
		);
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'status':
				$status = get_post_meta( $post_id, '_eb_status', true );
				echo '<span class="eb-status eb-status--' . esc_attr( $status ) . '">' . esc_html( EB_Orders::status_label( $status ) ) . '</span>';
				break;
			case 'event':
				$event_id = (int) get_post_meta( $post_id, '_eb_event_id', true );
				$title    = get_the_title( $event_id );
				if ( $event_id && $title ) {
					printf(
						'<a href="%s">%s</a>',
						esc_url( get_edit_post_link( $event_id ) ),
						esc_html( $title )
					);
				} else {
					echo '—';
				}
				break;
			case 'customer':
				$name  = get_post_meta( $post_id, '_eb_customer_name', true );
				$email = get_post_meta( $post_id, '_eb_customer_email', true );
				echo esc_html( $name );
				if ( $email ) {
					echo '<br /><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
				}
				break;
			case 'total':
				$total    = (float) get_post_meta( $post_id, '_eb_total', true );
				$currency = strtoupper( (string) get_post_meta( $post_id, '_eb_currency', true ) );
				echo esc_html( number_format_i18n( $total, 2 ) . ' ' . $currency );
				break;
		}
	}

	/**
	 * Meta boxes on order edit screen.
	 */
	public function meta_boxes() {
		remove_meta_box( 'submitdiv', 'eb_order', 'side' );

		add_meta_box(
			'eb_order_details',
			__( 'Ordredetaljer', 'eventbilletter' ),
			array( $this, 'render_details' ),
			'eb_order',
			'normal',
			'high'
		);
	}

	/**
	 * Render order details.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_details( $post ) {
		$order = EB_Orders::get( $post->ID );
		if ( ! $order ) {
			return;
		}

		$event_title = get_the_title( $order['event_id'] );
		?>
		<table class="widefat striped">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Ordrenummer', 'eventbilletter' ); ?></th>
					<td><?php echo esc_html( $order['order_number'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Status', 'eventbilletter' ); ?></th>
					<td><span class="eb-status eb-status--<?php echo esc_attr( $order['status'] ); ?>"><?php echo esc_html( EB_Orders::status_label( $order['status'] ) ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Event', 'eventbilletter' ); ?></th>
					<td>
						<?php if ( $order['event_id'] ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $order['event_id'] ) ); ?>"><?php echo esc_html( $event_title ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Kunde', 'eventbilletter' ); ?></th>
					<td><?php echo esc_html( $order['customer_name'] ); ?> &lt;<?php echo esc_html( $order['customer_email'] ); ?>&gt;</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Total', 'eventbilletter' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( $order['total'], 2 ) . ' ' . strtoupper( $order['currency'] ) ); ?></td>
				</tr>
				<?php if ( $order['paid_at'] ) : ?>
				<tr>
					<th><?php esc_html_e( 'Betalt', 'eventbilletter' ); ?></th>
					<td><?php echo esc_html( $order['paid_at'] ); ?></td>
				</tr>
				<?php endif; ?>
				<?php if ( $order['stripe_session_id'] ) : ?>
				<tr>
					<th><?php esc_html_e( 'Stripe session', 'eventbilletter' ); ?></th>
					<td><code><?php echo esc_html( $order['stripe_session_id'] ); ?></code></td>
				</tr>
				<?php endif; ?>
				<?php if ( $order['stripe_payment_intent'] ) : ?>
				<tr>
					<th><?php esc_html_e( 'Payment intent', 'eventbilletter' ); ?></th>
					<td><code><?php echo esc_html( $order['stripe_payment_intent'] ); ?></code></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Linjer', 'eventbilletter' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Billettype', 'eventbilletter' ); ?></th>
					<th><?php esc_html_e( 'Antal', 'eventbilletter' ); ?></th>
					<th><?php esc_html_e( 'Pris', 'eventbilletter' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( is_array( $order['line_items'] ) ) : ?>
					<?php foreach ( $order['line_items'] as $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item['type_name'] ?? '' ); ?></td>
							<td><?php echo esc_html( (string) ( $item['quantity'] ?? 0 ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (float) ( $item['line_total'] ?? 0 ), 2 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $order['ticket_codes'] ) && is_array( $order['ticket_codes'] ) ) : ?>
			<h3><?php esc_html_e( 'Billetkoder', 'eventbilletter' ); ?></h3>
			<ul>
				<?php foreach ( $order['ticket_codes'] as $group ) : ?>
					<?php
					$name  = $group['type_name'] ?? '';
					$codes = $group['codes'] ?? array();
					foreach ( (array) $codes as $code ) :
						?>
						<li><strong><?php echo esc_html( $name ); ?>:</strong> <code><?php echo esc_html( $code ); ?></code></li>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php
	}

	/**
	 * Simplify row actions.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( 'eb_order' === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}
}
