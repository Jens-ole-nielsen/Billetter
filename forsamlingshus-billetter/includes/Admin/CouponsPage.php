<?php
/**
 * Coupon administration.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Admin;

use Forsamlingshus\Billetter\Capabilities;
use Forsamlingshus\Billetter\CouponRepository;
use Forsamlingshus\Billetter\CouponRules;
use Forsamlingshus\Billetter\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Billetter → Rabatkoder.
 */
final class CouponsPage {

	public function __construct( private CouponRepository $coupons ) {
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_fhb_save_coupon', array( $this, 'save' ) );
		add_action( 'admin_post_fhb_coupon_active', array( $this, 'active' ) );
		add_action( 'admin_post_fhb_delete_coupon', array( $this, 'delete' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'fhb-tickets',
			__( 'Rabatkoder', 'forsamlingshus-billetter' ),
			__( 'Rabatkoder', 'forsamlingshus-billetter' ),
			Capabilities::ORDERS,
			'fhb-coupons',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! Capabilities::can( Capabilities::ORDERS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		$rows = $this->coupons->all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Rabatkoder', 'forsamlingshus-billetter' ); ?></h1>
			<p><?php esc_html_e( 'En kode kan give procent rabat, et antal gratis billetter, eller gøre hele ordren gratis. Tomt arrangement betyder alle arrangementer. Tomt maks. antal betyder ubegrænset.', 'forsamlingshus-billetter' ); ?></p>
			<h2><?php esc_html_e( 'Ny kode', 'forsamlingshus-billetter' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fhb_save_coupon' ); ?>
				<input type="hidden" name="action" value="fhb_save_coupon">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fhb-coupon-code"><?php esc_html_e( 'Kode', 'forsamlingshus-billetter' ); ?></label></th>
						<td><input class="regular-text" type="text" id="fhb-coupon-code" name="code" maxlength="40" required placeholder="SOMMER20"></td>
					</tr>
					<tr>
						<th scope="row"><label for="fhb-coupon-type"><?php esc_html_e( 'Type', 'forsamlingshus-billetter' ); ?></label></th>
						<td>
							<select id="fhb-coupon-type" name="type">
								<option value="percent"><?php esc_html_e( 'Procent rabat', 'forsamlingshus-billetter' ); ?></option>
								<option value="tickets"><?php esc_html_e( 'Gratis billetter', 'forsamlingshus-billetter' ); ?></option>
								<option value="free"><?php esc_html_e( 'Alle billetter gratis', 'forsamlingshus-billetter' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fhb-coupon-percent"><?php esc_html_e( 'Procent', 'forsamlingshus-billetter' ); ?></label></th>
						<td><input type="number" min="1" max="100" step="1" id="fhb-coupon-percent" name="percent" value="10"> <span class="description"><?php esc_html_e( 'Bruges ved procent rabat.', 'forsamlingshus-billetter' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><label for="fhb-coupon-tickets"><?php esc_html_e( 'Gratis billetter', 'forsamlingshus-billetter' ); ?></label></th>
						<td><input type="number" min="1" step="1" id="fhb-coupon-tickets" name="free_tickets" value="1"> <span class="description"><?php esc_html_e( 'De første billetter koster 0 kr. Resten koster fuld pris.', 'forsamlingshus-billetter' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><label for="fhb-coupon-event"><?php esc_html_e( 'Arrangement', 'forsamlingshus-billetter' ); ?></label></th>
						<td>
							<select id="fhb-coupon-event" name="event_id">
								<option value="0"><?php esc_html_e( 'Alle arrangementer', 'forsamlingshus-billetter' ); ?></option>
								<?php foreach ( Events::choices() as $event ) : ?>
									<option value="<?php echo esc_attr( (string) $event->ID ); ?>"><?php echo esc_html( get_the_title( $event ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fhb-coupon-max"><?php esc_html_e( 'Maks. antal brug', 'forsamlingshus-billetter' ); ?></label></th>
						<td><input type="number" min="1" step="1" id="fhb-coupon-max" name="max_uses" placeholder="<?php esc_attr_e( 'Ubegrænset', 'forsamlingshus-billetter' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="fhb-coupon-expires"><?php esc_html_e( 'Udløber', 'forsamlingshus-billetter' ); ?></label></th>
						<td><input type="date" id="fhb-coupon-expires" name="expires_on"> <span class="description"><?php esc_html_e( 'Valgfri. Tom betyder, at koden ikke udløber.', 'forsamlingshus-billetter' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'forsamlingshus-billetter' ); ?></th>
						<td><label><input type="checkbox" name="active" value="1" checked> <?php esc_html_e( 'Aktiv', 'forsamlingshus-billetter' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button( __( 'Opret kode', 'forsamlingshus-billetter' ) ); ?>
			</form>
			<h2><?php esc_html_e( 'Koder', 'forsamlingshus-billetter' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Kode', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Type', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Arrangement', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Brugt', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Status', 'forsamlingshus-billetter' ); ?></th>
						<th><?php esc_html_e( 'Handling', 'forsamlingshus-billetter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $rows ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'Ingen koder endnu.', 'forsamlingshus-billetter' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><code><?php echo esc_html( (string) $row->code ); ?></code></td>
							<td><?php echo esc_html( $this->value_label( $row ) ); ?></td>
							<td><?php echo esc_html( $this->event_label( (int) $row->event_id ) ); ?></td>
							<td>
								<?php
								$uses = $this->coupons->count_uses( (string) $row->code );
								$max = (int) $row->max_uses;
								echo esc_html( $max > 0 ? $uses . ' / ' . $max : (string) $uses );
								?>
							</td>
							<td>
								<?php
								if ( ! empty( $row->expires_on ) && wp_date( 'Y-m-d' ) > (string) $row->expires_on ) {
									esc_html_e( 'Udløbet', 'forsamlingshus-billetter' );
								} elseif ( empty( $row->active ) ) {
									esc_html_e( 'Inaktiv', 'forsamlingshus-billetter' );
								} else {
									esc_html_e( 'Aktiv', 'forsamlingshus-billetter' );
								}
								?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'fhb_coupon_active_' . (int) $row->id ); ?>
									<input type="hidden" name="action" value="fhb_coupon_active">
									<input type="hidden" name="coupon_id" value="<?php echo esc_attr( (string) $row->id ); ?>">
									<input type="hidden" name="active" value="<?php echo empty( $row->active ) ? '1' : '0'; ?>">
									<button type="submit" class="button button-small"><?php echo esc_html( empty( $row->active ) ? __( 'Aktivér', 'forsamlingshus-billetter' ) : __( 'Deaktivér', 'forsamlingshus-billetter' ) ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('Slet koden? Ordrer, der allerede har brugt den, beholder oplysningen.');">
									<?php wp_nonce_field( 'fhb_delete_coupon_' . (int) $row->id ); ?>
									<input type="hidden" name="action" value="fhb_delete_coupon">
									<input type="hidden" name="coupon_id" value="<?php echo esc_attr( (string) $row->id ); ?>">
									<button type="submit" class="button button-small"><?php esc_html_e( 'Slet', 'forsamlingshus-billetter' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function save(): void {
		if ( ! Capabilities::can( Capabilities::ORDERS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'fhb_save_coupon' );
		$code = isset( $_POST['code'] ) ? CouponRules::normalize( sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$percent = isset( $_POST['percent'] ) ? absint( $_POST['percent'] ) : 0;
		$free_tickets = isset( $_POST['free_tickets'] ) ? absint( $_POST['free_tickets'] ) : 0;
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$max_raw = isset( $_POST['max_uses'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['max_uses'] ) ) ) : '';
		$max_uses = '' === $max_raw ? 0 : absint( $max_raw );
		$expires = isset( $_POST['expires_on'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_on'] ) ) : '';
		$active = empty( $_POST['active'] ) ? 0 : 1;

		if ( ! CouponRules::valid_code( $code ) ) {
			Notices::flash( 'error', __( 'Koden skal være 2-40 tegn: bogstaver, tal, bindestreg eller understreg.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		if ( ! in_array( $type, array( CouponRules::PERCENT, CouponRules::TICKETS, CouponRules::FREE ), true ) ) {
			Notices::flash( 'error', __( 'Vælg en type.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		if ( CouponRules::PERCENT === $type && ( $percent < 1 || $percent > 100 ) ) {
			Notices::flash( 'error', __( 'Procenten skal være mellem 1 og 100.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		if ( CouponRules::TICKETS === $type && $free_tickets < 1 ) {
			Notices::flash( 'error', __( 'Angiv mindst én gratis billet.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		if ( $event_id > 0 && 'fhb_event' !== get_post_type( $event_id ) ) {
			Notices::flash( 'error', __( 'Arrangementet blev ikke fundet.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		if ( '' !== $expires && 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) ) {
			Notices::flash( 'error', __( 'Udløbsdatoen blev ikke genkendt.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		if ( $this->coupons->find_by_code( $code ) ) {
			Notices::flash( 'error', __( 'Koden findes allerede.', 'forsamlingshus-billetter' ) );
			$this->back();
		}

		$id = $this->coupons->insert(
			array(
				'code' => $code,
				'type' => $type,
				'percent' => CouponRules::PERCENT === $type ? $percent : 0,
				'free_tickets' => CouponRules::TICKETS === $type ? $free_tickets : 0,
				'event_id' => $event_id,
				'max_uses' => $max_uses,
				'active' => $active,
				'expires_on' => $expires,
			)
		);
		if ( $id < 1 ) {
			Notices::flash( 'error', __( 'Koden kunne ikke gemmes.', 'forsamlingshus-billetter' ) );
			$this->back();
		}
		Notices::flash( 'success', __( 'Rabatkoden er oprettet.', 'forsamlingshus-billetter' ) );
		$this->back();
	}

	public function active(): void {
		if ( ! Capabilities::can( Capabilities::ORDERS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		$id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
		check_admin_referer( 'fhb_coupon_active_' . $id );
		$on = ! empty( $_POST['active'] );
		$this->coupons->set_active( $id, $on );
		Notices::flash( 'success', $on ? __( 'Koden er aktiv.', 'forsamlingshus-billetter' ) : __( 'Koden er deaktiveret.', 'forsamlingshus-billetter' ) );
		$this->back();
	}

	public function delete(): void {
		if ( ! Capabilities::can( Capabilities::ORDERS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		$id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
		check_admin_referer( 'fhb_delete_coupon_' . $id );
		$this->coupons->delete( $id );
		Notices::flash( 'success', __( 'Koden er slettet.', 'forsamlingshus-billetter' ) );
		$this->back();
	}

	private function value_label( object $row ): string {
		return match ( (string) $row->type ) {
			CouponRules::TICKETS => sprintf(
				/* translators: %d: number of free tickets */
				__( '%d gratis billetter', 'forsamlingshus-billetter' ),
				(int) $row->free_tickets
			),
			CouponRules::FREE => __( 'Alle billetter gratis', 'forsamlingshus-billetter' ),
			default => sprintf(
				/* translators: %d: percent */
				__( '%d%% rabat', 'forsamlingshus-billetter' ),
				(int) $row->percent
			),
		};
	}

	private function event_label( int $event_id ): string {
		if ( $event_id < 1 ) {
			return __( 'Alle', 'forsamlingshus-billetter' );
		}
		$title = get_the_title( $event_id );

		return is_string( $title ) && '' !== $title ? $title : __( 'Arrangement', 'forsamlingshus-billetter' );
	}

	private function back(): never {
		wp_safe_redirect( admin_url( 'admin.php?page=fhb-coupons' ) );
		exit;
	}
}
