<?php
/**
 * Email buyers of one event.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Admin;

use Forsamlingshus\Billetter\Capabilities;
use Forsamlingshus\Billetter\Events;
use Forsamlingshus\Billetter\OrderRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Sends one message to the distinct email addresses that bought tickets for the chosen event.
 */
final class BroadcastPage {

	public function __construct( private OrderRepository $orders ) {
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_fhb_broadcast', array( $this, 'send' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'fhb-tickets',
			__( 'Skriv til købere', 'forsamlingshus-billetter' ),
			__( 'Skriv til købere', 'forsamlingshus-billetter' ),
			Capabilities::EMAIL,
			'fhb-broadcast',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! Capabilities::can( Capabilities::EMAIL ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
		$count = $event_id > 0 ? count( $this->orders->paid_emails( $event_id ) ) : 0;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Skriv til købere', 'forsamlingshus-billetter' ); ?></h1>
			<p><?php esc_html_e( 'Mailen sendes kun til købere med en betalt ordre på det valgte arrangement. Hver e-mailadresse får brevet én gang.', 'forsamlingshus-billetter' ); ?></p>
			<form method="get">
				<input type="hidden" name="page" value="fhb-broadcast">
				<label for="fhb-broadcast-event"><?php esc_html_e( 'Arrangement', 'forsamlingshus-billetter' ); ?></label>
				<select id="fhb-broadcast-event" name="event_id">
					<option value="0"><?php esc_html_e( 'Vælg arrangement', 'forsamlingshus-billetter' ); ?></option>
					<?php foreach ( Events::choices() as $post ) : ?>
						<option value="<?php echo esc_attr( (string) $post->ID ); ?>" <?php selected( $event_id, (int) $post->ID ); ?>><?php echo esc_html( get_the_title( $post ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Vis antal', 'forsamlingshus-billetter' ), 'secondary', '', false ); ?>
			</form>
			<?php if ( $event_id > 0 ) : ?>
				<p><strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of recipients */
							_n( '%d køber modtager mailen.', '%d købere modtager mailen.', $count, 'forsamlingshus-billetter' ),
							$count
						)
					);
					?>
				</strong></p>
				<?php if ( $count > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'fhb_broadcast' ); ?>
						<input type="hidden" name="action" value="fhb_broadcast">
						<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event_id ); ?>">
						<input type="hidden" name="expected_count" value="<?php echo esc_attr( (string) $count ); ?>">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="fhb-subject"><?php esc_html_e( 'Emne', 'forsamlingshus-billetter' ); ?></label></th>
								<td><input class="large-text" type="text" id="fhb-subject" name="subject" maxlength="150" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="fhb-message"><?php esc_html_e( 'Besked', 'forsamlingshus-billetter' ); ?></label></th>
								<td><textarea class="large-text" rows="10" id="fhb-message" name="message" maxlength="5000" required></textarea></td>
							</tr>
						</table>
						<label>
							<input type="checkbox" name="confirm_send" value="1" required>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of recipients */
									_n( 'Jeg sender til %d køber.', 'Jeg sender til %d købere.', $count, 'forsamlingshus-billetter' ),
									$count
								)
							);
							?>
						</label>
						<?php submit_button( __( 'Send mail', 'forsamlingshus-billetter' ) ); ?>
					</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public function send(): void {
		if ( ! Capabilities::can( Capabilities::EMAIL ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'fhb_broadcast' );
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$expected = isset( $_POST['expected_count'] ) ? absint( $_POST['expected_count'] ) : -1;
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$subject = trim( str_replace( array( "\r", "\n" ), '', $subject ) );
		$message = trim( $message );
		if ( $event_id < 1 || '' === $subject || '' === $message || empty( $_POST['confirm_send'] ) ) {
			Notices::flash( 'error', __( 'Udfyld emne og besked, og bekræft antallet.', 'forsamlingshus-billetter' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=fhb-broadcast&event_id=' . $event_id ) );
			exit;
		}
		$subject = mb_substr( $subject, 0, 150 );
		$message = mb_substr( $message, 0, 5000 );
		$emails = $this->orders->paid_emails( $event_id );
		if ( count( $emails ) !== $expected ) {
			Notices::flash( 'error', __( 'Antallet af købere er ændret. Tjek det, og send igen.', 'forsamlingshus-billetter' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=fhb-broadcast&event_id=' . $event_id ) );
			exit;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 120 );
		}
		$sent = 0;
		$failed = 0;
		foreach ( $emails as $email ) {
			if ( ! is_email( $email ) ) {
				++$failed;
				continue;
			}
			$ok = wp_mail(
				$email,
				$subject,
				$message,
				array( 'Content-Type: text/plain; charset=UTF-8' )
			);
			if ( $ok ) {
				++$sent;
			} else {
				++$failed;
			}
		}
		Notices::flash(
			'success',
			sprintf(
				/* translators: 1: sent count, 2: failed count */
				__( 'Sendt til %1$d. Fejlede: %2$d.', 'forsamlingshus-billetter' ),
				$sent,
				$failed
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=fhb-broadcast&event_id=' . $event_id ) );
		exit;
	}
}
