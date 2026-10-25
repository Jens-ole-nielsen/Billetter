<?php
/**
 * One row per ticket.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Admin;

use Forsamlingshus\Billetter\Capabilities;
use Forsamlingshus\Billetter\Events;
use Forsamlingshus\Billetter\Format;
use Forsamlingshus\Billetter\OrderNumber;
use Forsamlingshus\Billetter\TicketRepository;
use Forsamlingshus\Billetter\Token;

defined( 'ABSPATH' ) || exit;

/**
 * The buyer name is repeated on every ticket they bought.
 */
final class ParticipantsPage {

	private const PER_PAGE = 50;

	public function __construct( private TicketRepository $tickets ) {
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'fhb-tickets',
			__( 'Deltagere', 'forsamlingshus-billetter' ),
			__( 'Deltagere', 'forsamlingshus-billetter' ),
			Capabilities::ORDERS,
			'fhb-participants',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! Capabilities::can( Capabilities::ORDERS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$result = $this->tickets->admin_list( $event_id, self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE );
		$pages = max( 1, (int) ceil( $result['total'] / self::PER_PAGE ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Deltagere', 'forsamlingshus-billetter' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Én række pr. billet. Køberens navn står på hver billet i ordren.', 'forsamlingshus-billetter' ); ?></p>
			<form method="get">
				<input type="hidden" name="page" value="fhb-participants">
				<label class="screen-reader-text" for="fhb-participant-event"><?php esc_html_e( 'Arrangement', 'forsamlingshus-billetter' ); ?></label>
				<select id="fhb-participant-event" name="event_id">
					<option value="0"><?php esc_html_e( 'Alle arrangementer', 'forsamlingshus-billetter' ); ?></option>
					<?php foreach ( Events::choices() as $post ) : ?>
						<option value="<?php echo esc_attr( (string) $post->ID ); ?>" <?php selected( $event_id, (int) $post->ID ); ?>><?php echo esc_html( get_the_title( $post ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Vis', 'forsamlingshus-billetter' ), 'secondary', '', false ); ?>
			</form>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Billet', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Arrangement', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Køber', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'E-mail', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Telefon', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Ordre', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Bemærkning', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Indtjekning', 'forsamlingshus-billetter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $result['rows'] ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'Ingen billetter.', 'forsamlingshus-billetter' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $result['rows'] as $row ) : ?>
						<tr>
							<td>
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: ticket number, 2: quantity */
										__( 'Billet %1$d af %2$d', 'forsamlingshus-billetter' ),
										(int) $row->sequence_no,
										(int) $row->quantity
									)
								);
								?>
								<br><code><?php echo esc_html( Token::grouped( (string) $row->token ) ); ?></code>
							</td>
							<td><?php echo esc_html( get_the_title( (int) $row->event_id ) ); ?></td>
							<td><?php echo esc_html( (string) $row->buyer_name ); ?></td>
							<td><?php echo esc_html( (string) $row->buyer_email ); ?></td>
							<td><?php echo esc_html( (string) $row->buyer_phone ); ?></td>
							<td><?php echo esc_html( OrderNumber::format( (int) $row->order_id ) ); ?></td>
							<td><?php echo esc_html( (string) $row->remark ); ?></td>
							<td>
								<?php if ( 'checked_in' === (string) $row->status ) : ?>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: time */
											__( 'Fremmødt %s', 'forsamlingshus-billetter' ),
											Format::when( (string) $row->checked_in_at )
										)
									);
									?>
								<?php else : ?>
									<?php esc_html_e( 'Ikke brugt', 'forsamlingshus-billetter' ); ?>
								<?php endif; ?>
							</td>
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
}
