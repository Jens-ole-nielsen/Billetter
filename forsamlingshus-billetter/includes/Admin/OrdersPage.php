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
use Forsamlingshus\Billetter\RefundRules;
use Forsamlingshus\Billetter\StripeRefund;
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
		private TicketRepository $tickets,
		private StripeRefund $refunds
	) {
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_fhb_mark_paid', array( $this, 'mark_paid' ) );
		add_action( 'admin_post_fhb_cancel_order', array( $this, 'cancel' ) );
		add_action( 'admin_post_fhb_resend_tickets', array( $this, 'resend' ) );
		add_action( 'admin_post_fhb_view_tickets', array( $this, 'view_tickets' ) );
		add_action( 'admin_post_fhb_refund_order', array( $this, 'refund' ) );
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

		$focus = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		if ( $focus > 0 ) {
			$order = $this->orders->find( $focus );
			if ( $order ) {
				$this->render_order( $order );
				return;
			}
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Ordren blev ikke fundet.', 'forsamlingshus-billetter' ) . '</p></div>';
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
								<a href="<?php echo esc_url( $this->order_url( (int) $order->id ) ); ?>"><?php echo esc_html( OrderNumber::format( (int) $order->id ) ); ?></a><br>
								<span class="description"><?php echo esc_html( Format::when( (string) $order->created_at ) ); ?></span>
							</td>
							<td>
								<?php echo esc_html( (string) $order->buyer_name ); ?><br>
								<a href="mailto:<?php echo esc_attr( (string) $order->buyer_email ); ?>"><?php echo esc_html( (string) $order->buyer_email ); ?></a><br>
								<?php echo esc_html( (string) $order->buyer_phone ); ?>
							</td>
							<td><?php echo esc_html( $this->event_title( (int) $order->event_id ) ); ?></td>
							<td><?php echo esc_html( (string) (int) $order->quantity ); ?></td>
							<td><?php $this->amount_cell( $order ); ?></td>
							<td>
								<?php echo esc_html( OrderStatus::label( (string) $order->status ) ); ?>
								<?php $this->refund_lines( $order ); ?>
								<?php if ( OrderStatus::PAID === (string) $order->status && empty( $order->tickets_sent_at ) ) : ?>
									<br><span class="description"><?php esc_html_e( 'Billetter er ikke sendt.', 'forsamlingshus-billetter' ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== (string) $order->failure_reason ) : ?>
									<br><span class="description"><?php echo esc_html( (string) $order->failure_reason ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) $order->remark ); ?></td>
							<td><?php $this->payment_cell( $order ); ?></td>
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
		if ( ! $this->paid_with_tickets( $order_id ) || ! $this->has_active_ticket( $this->tickets->for_order( $order_id ) ) ) {
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
		$ticket_rows = $this->tickets->for_order( (int) $order->id );
		if ( OrderStatus::PAID === (string) $order->status && array() !== $ticket_rows ) {
			$this->view_link( (int) $order->id );
			if ( $this->has_active_ticket( $ticket_rows ) ) {
				$this->action_form( 'fhb_resend_tickets', (int) $order->id, __( 'Send billetter igen', 'forsamlingshus-billetter' ) );
			}
		}
		if ( $this->can_refund( $order ) ) {
			echo '<p style="margin:0 0 6px;"><a class="button button-secondary" href="' . esc_url( $this->order_url( (int) $order->id ) . '#fhb-refund' ) . '">' . esc_html__( 'Refunder', 'forsamlingshus-billetter' ) . '</a></p>';
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

	public function refund(): void {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! Capabilities::can( Capabilities::ORDERS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'fhb_refund_order_' . $order_id );

		$mode = isset( $_POST['refund_mode'] ) ? sanitize_key( wp_unslash( $_POST['refund_mode'] ) ) : '';
		$requested = null;
		$cancel = 0;
		if ( 'partial' === $mode ) {
			$raw_amount = isset( $_POST['refund_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['refund_amount'] ) ) : '';
			$requested = Money::parse_ore( $raw_amount );
			$raw_count = isset( $_POST['cancel_count'] ) ? sanitize_text_field( wp_unslash( $_POST['cancel_count'] ) ) : '';
			if ( 1 !== preg_match( '/^\d+$/', $raw_count ) ) {
				Notices::flash( 'error', __( 'Antallet af billetter er ugyldigt. Ordren er ikke ændret.', 'forsamlingshus-billetter' ) );
				$this->back_to_order( $order_id );
			}
			$cancel = (int) $raw_count;
		}

		$result = $this->refunds->refund( $order_id, $mode, $requested, $cancel );
		Notices::flash( $result['ok'] ? 'success' : 'error', $result['message'] );
		$this->back_to_order( $order_id );
	}

	private function render_order( object $order ): void {
		$order_id = (int) $order->id;
		$tickets = $this->tickets->for_order( $order_id );
		$refunded = (int) ( $order->refunded_ore ?? 0 );
		$remaining = max( 0, (int) $order->amount_ore - $refunded );
		$unused = 0;
		foreach ( $tickets as $ticket ) {
			if ( 'valid' === (string) $ticket->status ) {
				++$unused;
			}
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( OrderNumber::format( $order_id ) ); ?></h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=fhb-orders' ) ); ?>"><?php esc_html_e( 'Tilbage til ordrer', 'forsamlingshus-billetter' ); ?></a></p>
			<table class="widefat striped" style="max-width:760px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Køber', 'forsamlingshus-billetter' ); ?></th>
						<td>
							<?php echo esc_html( (string) $order->buyer_name ); ?><br>
							<a href="mailto:<?php echo esc_attr( (string) $order->buyer_email ); ?>"><?php echo esc_html( (string) $order->buyer_email ); ?></a><br>
							<?php echo esc_html( (string) $order->buyer_phone ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Arrangement', 'forsamlingshus-billetter' ); ?></th>
						<td><?php echo esc_html( $this->event_title( (int) $order->event_id ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Antal', 'forsamlingshus-billetter' ); ?></th>
						<td><?php echo esc_html( (string) (int) $order->quantity ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Bemærkning', 'forsamlingshus-billetter' ); ?></th>
						<td><?php echo esc_html( (string) $order->remark ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'forsamlingshus-billetter' ); ?></th>
						<td>
							<?php echo esc_html( OrderStatus::label( (string) $order->status ) ); ?>
							<?php $this->refund_lines( $order ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Betaling', 'forsamlingshus-billetter' ); ?></th>
						<td><?php $this->payment_cell( $order ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Beløb', 'forsamlingshus-billetter' ); ?></th>
						<td><?php $this->amount_cell( $order ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Refunderet', 'forsamlingshus-billetter' ); ?></th>
						<td><?php echo esc_html( Money::format_ore( $refunded ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Kan stadig refunderes', 'forsamlingshus-billetter' ); ?></th>
						<td><?php echo esc_html( Money::format_ore( $remaining ) ); ?></td>
					</tr>
				</tbody>
			</table>
			<h2><?php esc_html_e( 'Billetter', 'forsamlingshus-billetter' ); ?></h2>
			<table class="widefat striped" style="max-width:760px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Billet', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Status', 'forsamlingshus-billetter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $tickets ) : ?>
						<tr><td colspan="2"><?php esc_html_e( 'Ingen billetter.', 'forsamlingshus-billetter' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $tickets as $ticket ) : ?>
						<tr>
							<td><?php echo esc_html( (string) (int) $ticket->sequence_no ); ?></td>
							<td><?php echo esc_html( $this->ticket_status_label( (string) $ticket->status ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<h2><?php esc_html_e( 'Handling', 'forsamlingshus-billetter' ); ?></h2>
			<?php $this->actions( $order ); ?>
			<?php $this->refund_forms( $order, $remaining, $unused ); ?>
		</div>
		<?php
	}

	private function refund_forms( object $order, int $remaining, int $unused ): void {
		if ( ! $this->can_refund( $order ) ) {
			echo '<h2 id="fhb-refund">' . esc_html__( 'Refundering', 'forsamlingshus-billetter' ) . '</h2>';
			if ( 'invoice' === (string) $order->payment_method ) {
				echo '<p>' . esc_html__( 'Fakturaordrer kan ikke refunderes via Stripe.', 'forsamlingshus-billetter' ) . '</p>';
			} elseif ( 'stripe' === (string) $order->payment_method && OrderStatus::PAID !== (string) $order->status ) {
				echo '<p>' . esc_html__( 'Kun en betalt Stripe-ordre kan refunderes.', 'forsamlingshus-billetter' ) . '</p>';
			} elseif ( 'stripe' === (string) $order->payment_method ) {
				echo '<p>' . esc_html__( 'Hele beløbet er refunderet.', 'forsamlingshus-billetter' ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'Denne ordre kan ikke refunderes via Stripe.', 'forsamlingshus-billetter' ) . '</p>';
			}
			return;
		}

		$order_id = (int) $order->id;
		?>
		<h2 id="fhb-refund"><?php esc_html_e( 'Refundering via Stripe', 'forsamlingshus-billetter' ); ?></h2>
		<p><?php esc_html_e( 'Beløbet sendes til Stripe som en refundering af betalingen. Hvis Stripe afviser det, ændres ordren ikke.', 'forsamlingshus-billetter' ); ?></p>
		<h3><?php esc_html_e( 'Hele restbeløbet', 'forsamlingshus-billetter' ); ?></h3>
		<p><?php echo esc_html( sprintf( /* translators: %s: amount */ __( 'Refunderer %s, annullerer ordrens billetter, så de ikke kan tjekkes ind, og lægger pladserne tilbage som ledige.', 'forsamlingshus-billetter' ), Money::format_ore( $remaining ) ) ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'fhb_refund_order_' . $order_id ); ?>
			<input type="hidden" name="action" value="fhb_refund_order">
			<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order_id ); ?>">
			<input type="hidden" name="refund_mode" value="full">
			<?php submit_button( __( 'Refunder hele restbeløbet', 'forsamlingshus-billetter' ), 'secondary', 'submit', false ); ?>
		</form>
		<h3><?php esc_html_e( 'Del af beløbet', 'forsamlingshus-billetter' ); ?></h3>
		<p><?php echo esc_html( sprintf( /* translators: 1: amount, 2: unused tickets */ __( 'Højst %1$s. Ubrugte billetter: %2$d. Skriv 0, hvis kun pengene skal tilbage.', 'forsamlingshus-billetter' ), Money::format_ore( $remaining ), $unused ) ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'fhb_refund_order_' . $order_id ); ?>
			<input type="hidden" name="action" value="fhb_refund_order">
			<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order_id ); ?>">
			<input type="hidden" name="refund_mode" value="partial">
			<p>
				<label for="fhb-refund-amount"><?php esc_html_e( 'Beløb i kroner', 'forsamlingshus-billetter' ); ?></label><br>
				<input id="fhb-refund-amount" name="refund_amount" type="text" inputmode="decimal" class="regular-text" required>
			</p>
			<p>
				<label for="fhb-refund-count"><?php esc_html_e( 'Ubrugte billetter der annulleres', 'forsamlingshus-billetter' ); ?></label><br>
				<input id="fhb-refund-count" name="cancel_count" type="number" min="0" max="<?php echo esc_attr( (string) $unused ); ?>" value="0" step="1" required>
			</p>
			<?php submit_button( __( 'Refunder delvist', 'forsamlingshus-billetter' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	private function amount_cell( object $order ): void {
		echo esc_html( Money::format_ore( (int) $order->amount_ore ) );
		$code = isset( $order->coupon_code ) ? (string) $order->coupon_code : '';
		$discount = isset( $order->discount_ore ) ? (int) $order->discount_ore : 0;
		if ( '' === $code && $discount < 1 ) {
			return;
		}
		echo '<br><span class="description">';
		if ( '' !== $code ) {
			echo esc_html( $code );
		}
		if ( $discount > 0 ) {
			echo esc_html( ( '' !== $code ? ' · ' : '' ) . sprintf(
				/* translators: %s: discount amount */
				__( 'Rabat %s', 'forsamlingshus-billetter' ),
				Money::format_ore( $discount )
			) );
		}
		echo '</span>';
	}

	private function payment_cell( object $order ): void {
		$method = (string) $order->payment_method;
		if ( 'stripe' === $method ) {
			echo '<strong>' . esc_html__( 'Stripe', 'forsamlingshus-billetter' ) . '</strong>';
			echo '<br><span class="description">' . esc_html__( 'Kort / MobilePay', 'forsamlingshus-billetter' ) . '</span>';
			return;
		}
		echo esc_html( OrderStatus::payment_label( $method ) );
	}

	private function refund_lines( object $order ): void {
		$refunded = (int) ( $order->refunded_ore ?? 0 );
		$settlement = RefundRules::settlement( (int) $order->amount_ore, $refunded );
		if ( 'full' === $settlement ) {
			echo '<br><span class="description">' . esc_html__( 'Fuldt refunderet', 'forsamlingshus-billetter' ) . '</span>';
		} elseif ( 'partial' === $settlement ) {
			echo '<br><span class="description">' . esc_html( sprintf( /* translators: %s: amount */ __( 'Delvist refunderet: %s', 'forsamlingshus-billetter' ), Money::format_ore( $refunded ) ) ) . '</span>';
		}
		foreach ( RefundRules::ids( (string) ( $order->stripe_refund_ids ?? '' ) ) as $refund_id ) {
			echo '<br><code>' . esc_html( $refund_id ) . '</code>';
		}
	}

	private function order_url( int $order_id ): string {
		return add_query_arg(
			array(
				'page' => 'fhb-orders',
				'order_id' => $order_id,
			),
			admin_url( 'admin.php' )
		);
	}

	private function can_refund( object $order ): bool {
		$plan = RefundRules::plan(
			(string) $order->payment_method,
			(string) $order->status,
			(int) $order->amount_ore,
			(int) ( $order->refunded_ore ?? 0 ),
			0,
			'full',
			null,
			0,
			null
		);

		return $plan['ok'];
	}

	/**
	 * @param list<object> $tickets
	 */
	private function has_active_ticket( array $tickets ): bool {
		foreach ( $tickets as $ticket ) {
			if ( 'cancelled' !== (string) $ticket->status ) {
				return true;
			}
		}

		return false;
	}

	private function ticket_status_label( string $status ): string {
		return match ( $status ) {
			'checked_in' => __( 'Fremmødt', 'forsamlingshus-billetter' ),
			'cancelled' => __( 'Annulleret', 'forsamlingshus-billetter' ),
			default => __( 'Ikke brugt', 'forsamlingshus-billetter' ),
		};
	}

	private function back_to_order( int $order_id ): void {
		wp_safe_redirect( $this->order_url( $order_id ) );
		exit;
	}

	private function back(): void {
		$ref = wp_get_referer();
		$target = is_string( $ref ) && str_contains( $ref, 'page=fhb-orders' ) ? $ref : admin_url( 'admin.php?page=fhb-orders' );
		wp_safe_redirect( $target );
		exit;
	}
}
