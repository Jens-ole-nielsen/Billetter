<?php
/**
 * Door screen: camera scan and manual code.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Admin;

use Forsamlingshus\Billetter\Capabilities;
use Forsamlingshus\Billetter\Checkin;

defined( 'ABSPATH' ) || exit;

/**
 * Staff-only. A scan marks that one ticket as arrived.
 */
final class CheckinPage {

	public function __construct( private Checkin $checkin ) {
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'wp_ajax_fhb_checkin', array( $this, 'ajax' ) );
		add_action( 'admin_post_fhb_checkin_manual', array( $this, 'manual' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'fhb-tickets',
			__( 'Indtjekning', 'forsamlingshus-billetter' ),
			__( 'Indtjekning', 'forsamlingshus-billetter' ),
			Capabilities::CHECKIN,
			'fhb-checkin',
			array( $this, 'render' )
		);
	}

	public function assets( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'fhb-checkin' !== $page && false === strpos( $hook, 'fhb-checkin' ) ) {
			return;
		}
		wp_enqueue_style( 'fhb-admin', FHB_URL . 'assets/css/admin.css', array(), FHB_VERSION );
		wp_enqueue_script( 'fhb-jsqr', FHB_URL . 'assets/js/jsQR.min.js', array(), '1.4.0', true );
		wp_enqueue_script( 'fhb-checkin', FHB_URL . 'assets/js/checkin.js', array( 'fhb-jsqr' ), FHB_VERSION, true );
		wp_localize_script(
			'fhb-checkin',
			'fhbCheckin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'fhb_checkin' ),
				'i18n' => array(
					'needHttps' => __( 'Kameraet virker kun på en sikker forbindelse (HTTPS). Indtast koden i stedet.', 'forsamlingshus-billetter' ),
					'cameraError' => __( 'Kameraet kunne ikke åbnes. Indtast koden i stedet.', 'forsamlingshus-billetter' ),
					'network' => __( 'Netværksfejl. Prøv igen.', 'forsamlingshus-billetter' ),
					'unused' => __( 'Stadig ubrugte billetter:', 'forsamlingshus-billetter' ),
					'noneUnused' => __( 'Alle billetter i ordren er brugt.', 'forsamlingshus-billetter' ),
					'valid' => __( 'Ikke brugt', 'forsamlingshus-billetter' ),
					'checked' => __( 'Fremmødt', 'forsamlingshus-billetter' ),
				),
			)
		);
	}

	public function render(): void {
		if ( ! Capabilities::can_checkin() ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		$stored = get_transient( 'fhb_checkin_result_' . get_current_user_id() );
		if ( is_array( $stored ) ) {
			delete_transient( 'fhb_checkin_result_' . get_current_user_id() );
		} else {
			$stored = null;
		}
		?>
		<div class="wrap fhb-checkin">
			<h1><?php esc_html_e( 'Indtjekning', 'forsamlingshus-billetter' ); ?></h1>
			<p><?php esc_html_e( 'Scan QR-koden med kameraet, eller indtast koden fra billetten. Én scanning markerer kun den ene billet som fremmødt.', 'forsamlingshus-billetter' ); ?></p>
			<p class="description"><?php esc_html_e( 'Kameraet kræver HTTPS.', 'forsamlingshus-billetter' ); ?></p>
			<p><button type="button" class="button button-primary" id="fhb-start-camera"><?php esc_html_e( 'Start kamera', 'forsamlingshus-billetter' ); ?></button></p>
			<video id="fhb-video" hidden autoplay playsinline></video>
			<canvas id="fhb-canvas" hidden></canvas>
			<form id="fhb-manual" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fhb_checkin', 'fhb_checkin_nonce' ); ?>
				<input type="hidden" name="action" value="fhb_checkin_manual">
				<label for="fhb-token"><?php esc_html_e( 'Kode', 'forsamlingshus-billetter' ); ?></label>
				<input id="fhb-token" name="token" autocomplete="off" autocapitalize="none" spellcheck="false" inputmode="text">
				<?php submit_button( __( 'Tjek ind', 'forsamlingshus-billetter' ), 'secondary', 'submit', false ); ?>
			</form>
			<div id="fhb-checkin-result" aria-live="polite">
				<?php if ( is_array( $stored ) ) : ?>
					<?php $this->render_result( $stored ); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function ajax(): void {
		if ( ! Capabilities::can_checkin() ) {
			wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'forsamlingshus-billetter' ) ), 403 );
		}
		check_ajax_referer( 'fhb_checkin', 'fhb_checkin_nonce' );
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		wp_send_json_success( $this->checkin->scan( $token, get_current_user_id() ) );
	}

	public function manual(): void {
		if ( ! Capabilities::can_checkin() ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'fhb_checkin', 'fhb_checkin_nonce' );
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$result = $this->checkin->scan( $token, get_current_user_id() );
		set_transient( 'fhb_checkin_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=fhb-checkin' ) );
		exit;
	}

	/**
	 * @param array<string, mixed> $result
	 */
	private function render_result( array $result ): void {
		$result_code = isset( $result['result'] ) ? (string) $result['result'] : 'unknown';
		$class = 'checked_in' === $result_code ? 'is-ok' : ( 'already' === $result_code ? 'is-warn' : 'is-bad' );
		echo '<div class="fhb-checkin-card ' . esc_attr( $class ) . '">';
		echo '<p><strong>' . esc_html( (string) ( $result['message'] ?? '' ) ) . '</strong></p>';
		if ( ! empty( $result['buyer'] ) ) {
			echo '<p>' . esc_html( (string) $result['buyer'] ) . '</p>';
		}
		if ( ! empty( $result['event'] ) ) {
			echo '<p>' . esc_html( (string) $result['event'] ) . '</p>';
		}
		$unused = isset( $result['unused'] ) && is_array( $result['unused'] ) ? $result['unused'] : array();
		if ( isset( $result['tickets'] ) && is_array( $result['tickets'] ) && array() !== $result['tickets'] ) {
			if ( array() === $unused ) {
				echo '<p>' . esc_html__( 'Alle billetter i ordren er brugt.', 'forsamlingshus-billetter' ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'Stadig ubrugte billetter:', 'forsamlingshus-billetter' ) . ' ' . esc_html( implode( ', ', array_map( 'strval', $unused ) ) ) . '</p>';
			}
			echo '<ul>';
			foreach ( $result['tickets'] as $ticket ) {
				if ( ! is_array( $ticket ) ) {
					continue;
				}
				$status = (string) ( $ticket['status'] ?? '' );
				if ( 'checked_in' === $status ) {
					$label = __( 'Fremmødt', 'forsamlingshus-billetter' ) . ' ' . (string) ( $ticket['checked_in_at'] ?? '' );
				} elseif ( 'cancelled' === $status ) {
					$label = __( 'Annulleret', 'forsamlingshus-billetter' );
				} else {
					$label = __( 'Ikke brugt', 'forsamlingshus-billetter' );
				}
				echo '<li>' . esc_html( sprintf( /* translators: 1: sequence, 2: status */ __( 'Billet %1$s: %2$s', 'forsamlingshus-billetter' ), (string) ( $ticket['sequence'] ?? '' ), $label ) ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}
}
