<?php
/**
 * Order list and PDF export.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Admin;

use Forsamlingshus\Billetter\Capabilities;
use Forsamlingshus\Billetter\Events;
use Forsamlingshus\Billetter\Format;
use Forsamlingshus\Billetter\Fulfillment;
use Forsamlingshus\Billetter\Mail\TicketMailer;
use Forsamlingshus\Billetter\Money;
use Forsamlingshus\Billetter\OrderNumber;
use Forsamlingshus\Billetter\OrderRepository;
use Forsamlingshus\Billetter\OrderStatus;
use Forsamlingshus\Billetter\Pdf\OrdersPdf;
use Forsamlingshus\Billetter\TicketRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Buyer, event, quantity, amount, status, remark and payment method.
 */
final class OrdersPage {

	private const PER_PAGE = 20;

	public function __construct(
		private OrderRepository $orders,
		private Fulfillment $fulfillment,
		private TicketMailer $mailer,
		private TicketRepository $tickets
	) {
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_fhb_mark_paid', array( $this, 'mark_paid' ) );
		add_action( 'admin_post_fhb_cancel_order', array( $this, 'cancel' ) );
		add_action( 'admin_post_fhb_resend_tickets', array( $this, 'resend' ) );
		add_action( 'admin_post_fhb_view_tickets', array( $this, 'view_tickets' ) );
		add_action( 'admin_post_fhb_export_orders', array( $this, 'export' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'fhb-tickets',
			__( 'Ordrer', 'forsamlingshus-billetter' ),
			__( 'Ordrer', 'forsamlingshus-billetter' ),
			Capabilities::ORDERS,
			'fhb-orders',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! Capabilities::can( Capabilities::ORDERS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}

		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$result = $this->orders->query( $event_id, $status, $search, self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE );
		$pages = max( 1, (int) ceil( $result['total'] / self::PER_PAGE ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ordrer', 'forsamlingshus-billetter' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="fhb-orders">
				<?php $this->event_select( $event_id ); ?>
				<select name="status">
					<option value=""><?php esc_html_e( 'Alle statusser', 'forsamlingshus-billetter' ); ?></option>
					<?php foreach ( OrderStatus::all() as $slug ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $status, $slug ); ?>><?php echo esc_html( OrderStatus::label( $slug ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="screen-reader-text" for="fhb-order-search"><?php esc_html_e( 'Søg', 'forsamlingshus-billetter' ); ?></label>
				<input id="fhb-order-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Navn, e-mail, telefon eller bemærkning', 'forsamlingshus-billetter' ); ?>">
				<?php submit_button( __( 'Filtrér', 'forsamlingshus-billetter' ), 'secondary', '', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0;">
				<?php wp_nonce_field( 'fhb_export_orders' ); ?>
				<input type="hidden" name="action" value="fhb_export_orders">
				<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event_id ); ?>">
				<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
				<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
				<?php submit_button( __( 'Eksporter PDF', 'forsamlingshus-billetter' ), 'secondary', 'submit', false ); ?>
			</form>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Ordre', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Køber', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Arrangement', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Antal', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Beløb', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Status', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Bemærkning', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Betaling', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Handling', 'forsamlingshus-billetter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $result['rows'] ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'Ingen ordrer.', 'forsamlingshus-billetter' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $result['rows'] as $order ) : ?>
						<tr>
							<td>
								<?php echo esc_html( OrderNumber::format( (int) $order->id ) ); ?><br>
								<span class="description"><?php echo esc_html( Format::when( (string) $order->created_at ) ); ?></span>
							</td>
							<td>
								<?php echo esc_html( (string) $order->buyer_name ); ?><br>
								<a href="mailto:<?php echo esc_attr( (string) $order->buyer_email ); ?>"><?php echo esc_html( (string) $order->buyer_email ); ?></a><br>
								<?php echo esc_html( (string) $order->buyer_phone ); ?>
							</td>
							<td><?php echo esc_html( $this->event_title( (int) $order->event_id ) ); ?></td>
							<td><?php echo esc_html( (string) (int) $order->quantity ); ?></td>
							<td><?php echo esc_html( Money::format_ore( (int) $order->amount_ore ) ); ?></td>
							<td>
								<?php echo esc_html( OrderStatus::label( (string) $order->status ) ); ?>
								<?php if ( OrderStatus::PAID === (string) $order->status && empty( $order->tickets_sent_at ) ) : ?>
									<br><span class="description"><?php esc_html_e( 'Billetter er ikke sendt.', 'forsamlingshus-billetter' ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== (string) $order->failure_reason ) : ?>
									<br><span class="description"><?php echo esc_html( (string) $order->failure_reason ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) $order->remark ); ?></td>
							<td><?php echo esc_html( OrderStatus::payment_label( (string) $order->payment_method ) ); ?></td>
							<td><?php $this->actions( $order ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			if ( $pages > 1 ) {
				echo wp_kses_post(
					(string) paginate_links(
						array(
							'base' => add_query_arg( 'paged', '%#%' ),
							'format' => '',
							'current' => $paged,
							'total' => $pages,
							'type' => 'list',
						)
					)
				);
			}
			?>
		</div>
		<?php
	}

	public function mark_paid(): void {
		$this->guard( 'fhb_mark_paid' );
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order = $this->orders->find( $order_id );
		if ( ! $order || 'invoice' !== (string) $order->payment_method || OrderStatus::AWAITING !== (string) $order->status ) {
			Notices::flash( 'error', __( 'Ordren kan ikke markeres som betalt.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		$result = $this->fulfillment->mark_paid( $order_id, '' );
		if ( ! $result['ok'] ) {
			Notices::flash( 'error', __( 'Ordren blev ikke opdateret.', 'forsamlingshus-billetter' ) );
		} elseif ( ! $result['mailed'] ) {
			Notices::flash( 'error', __( 'Ordren er betalt, men e-mailen med billetter kunne ikke sendes. Brug Send billetter igen.', 'forsamlingshus-billetter' ) );
		} else {
			Notices::flash( 'success', __( 'Ordren er markeret som betalt, og billetterne er sendt.', 'forsamlingshus-billetter' ) );
		}
		$this->back();
	}

	public function cancel(): void {
		$this->guard( 'fhb_cancel_order' );
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$outcome = $this->fulfillment->cancel_unpaid( $order_id, true );
		if ( 'cancelled' === $outcome ) {
			Notices::flash( 'success', __( 'Ordren er annulleret, og billetterne er ledige igen.', 'forsamlingshus-billetter' ) );
		} elseif ( 'paid' === $outcome ) {
			Notices::flash( 'success', __( 'Betalingen var allerede gennemført, så ordren er markeret som betalt.', 'forsamlingshus-billetter' ) );
		} elseif ( 'retry' === $outcome ) {
			Notices::flash( 'error', __( 'Ordren kunne ikke annulleres endnu, fordi Stripe ikke svarede. Prøv igen.', 'forsamlingshus-billetter' ) );
		} else {
			Notices::flash( 'error', __( 'Ordren kan ikke annulleres.', 'forsamlingshus-billetter' ) );
		}
		$this->back();
	}

	public function resend(): void {
		$this->guard( 'fhb_resend_tickets' );
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $this->paid_with_tickets( $order_id ) ) {
			Notices::flash( 'error', __( 'Billetterne kunne ikke sendes.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		if ( $this->fulfillment->resend( $order_id ) ) {
			Notices::flash( 'success', __( 'Billetterne er sendt igen.', 'forsamlingshus-billetter' ) );
		} else {
			Notices::flash( 'error', __( 'Billetterne kunne ikke sendes.', 'forsamlingshus-billetter' ) );
		}
		$this->back();
	}

	public function view_tickets(): void {
		$order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
		if ( ! Capabilities::can( Capabilities::ORDERS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'fhb_view_tickets_' . $order_id );
		$order = $this->orders->find( $order_id );
		if ( ! $order || ! $this->paid_with_tickets( $order_id ) ) {
			Notices::flash( 'error', __( 'Der er ingen billet-PDF for den ordre.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		$binary = $this->mailer->pdf_for_order( $order );
		if ( ! is_string( $binary ) || ! str_starts_with( $binary, '%PDF' ) ) {
			Notices::flash( 'error', __( 'Billet-PDF kunne ikke laves.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		$filename = 'billetter-' . OrderNumber::format( $order_id ) . '.pdf';
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $binary ) );
		echo $binary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF download.
		exit;
	}

	public function export(): void {
		if ( ! Capabilities::can( Capabilities::ORDERS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'fhb_export_orders' );
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		$result = $this->orders->query( $event_id, $status, $search, 5000, 0 );
		$rows = array();
		foreach ( $result['rows'] as $order ) {
			$rows[] = array(
				'number' => OrderNumber::format( (int) $order->id ),
				'buyer' => (string) $order->buyer_name,
				'quantity' => (int) $order->quantity,
				'remark' => (string) $order->remark,
				'amount' => Money::format_ore( (int) $order->amount_ore ),
			);
		}
		$title = __( 'Ordrer', 'forsamlingshus-billetter' );
		$subtitle = $event_id > 0 ? $this->event_title( $event_id ) : __( 'Alle arrangementer', 'forsamlingshus-billetter' );
		$binary = ( new OrdersPdf() )->render( $rows, $title, $subtitle );
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="ordrer.pdf"' );
		header( 'Content-Length: ' . strlen( $binary ) );
		echo $binary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF download.
		exit;
	}

	private function actions( object $order ): void {
		$unpaid = in_array( (string) $order->status, array( OrderStatus::PENDING, OrderStatus::AWAITING ), true );
		if ( 'invoice' === (string) $order->payment_method && OrderStatus::AWAITING === (string) $order->status ) {
			$this->action_form( 'fhb_mark_paid', (int) $order->id, __( 'Markér betalt', 'forsamlingshus-billetter' ) );
		}
		if ( $unpaid ) {
			$this->action_form( 'fhb_cancel_order', (int) $order->id, __( 'Annuller', 'forsamlingshus-billetter' ) );
		}
		if ( OrderStatus::PAID === (string) $order->status && array() !== $this->tickets->for_order( (int) $order->id ) ) {
			$this->view_link( (int) $order->id );
			$this->action_form( 'fhb_resend_tickets', (int) $order->id, __( 'Send billetter igen', 'forsamlingshus-billetter' ) );
		}
	}

	private function view_link( int $order_id ): void {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'fhb_view_tickets',
					'order_id' => $order_id,
				),
				admin_url( 'admin-post.php' )
			),
			'fhb_view_tickets_' . $order_id
		);
		echo '<p style="margin:0 0 6px;"><a class="button button-secondary" target="_blank" rel="noopener" href="' . esc_url( $url ) . '">' . esc_html__( 'Se billet-PDF', 'forsamlingshus-billetter' ) . '</a></p>';
	}

	private function paid_with_tickets( int $order_id ): bool {
		$order = $this->orders->find( $order_id );

		return $order
			&& OrderStatus::PAID === (string) $order->status
			&& array() !== $this->tickets->for_order( $order_id );
	}

	private function action_form( string $action, int $order_id, string $label ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0 0 6px;">';
		wp_nonce_field( $action );
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="order_id" value="' . esc_attr( (string) $order_id ) . '">';
		submit_button( $label, 'secondary small', 'submit', false );
		echo '</form>';
	}

	private function event_select( int $selected ): void {
		echo '<label class="screen-reader-text" for="fhb-order-event">' . esc_html__( 'Arrangement', 'forsamlingshus-billetter' ) . '</label>';
		echo '<select id="fhb-order-event" name="event_id">';
		echo '<option value="0">' . esc_html__( 'Alle arrangementer', 'forsamlingshus-billetter' ) . '</option>';
		foreach ( Events::choices() as $post ) {
			echo '<option value="' . esc_attr( (string) $post->ID ) . '" ' . selected( $selected, (int) $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>';
		}
		echo '</select> ';
	}

	private function event_title( int $event_id ): string {
		$post = get_post( $event_id );
		if ( ! $post ) {
			return __( '(slettet arrangement)', 'forsamlingshus-billetter' );
		}

		return get_the_title( $post );
	}

	private function guard( string $action ): void {
		if ( ! Capabilities::can( Capabilities::ORDERS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	private function back(): void {
		$ref = wp_get_referer();
		$target = is_string( $ref ) && str_contains( $ref, 'page=fhb-orders' ) ? $ref : admin_url( 'admin.php?page=fhb-orders' );
		wp_safe_redirect( $target );
		exit;
	}
}
