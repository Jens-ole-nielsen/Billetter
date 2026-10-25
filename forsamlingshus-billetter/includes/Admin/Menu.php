<?php
/**
 * Admin menu.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Admin;

use Forsamlingshus\Billetter\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * One menu for the hall. Door staff only see check-in.
 */
final class Menu {

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'register' ) );
		add_action( 'load-toplevel_page_fhb-tickets', array( $this, 'redirect_home' ) );
		add_filter( 'login_redirect', array( $this, 'door_login' ), 10, 3 );
		add_filter( 'plugin_action_links_' . plugin_basename( FHB_FILE ), array( $this, 'links' ) );
	}

	/**
	 * Door staff have no other admin home, so login opens check-in.
	 *
	 * @param string           $redirect           Default redirect.
	 * @param string           $requested          Requested redirect.
	 * @param \WP_User|\WP_Error $user             Logged-in user.
	 */
	public function door_login( string $redirect, string $requested, $user ): string {
		unset( $requested );
		if ( $user instanceof \WP_User && in_array( 'fhb_door', (array) $user->roles, true ) ) {
			return admin_url( 'admin.php?page=fhb-checkin' );
		}

		return $redirect;
	}

	public function register(): void {
		add_menu_page(
			__( 'Billetter', 'forsamlingshus-billetter' ),
			__( 'Billetter', 'forsamlingshus-billetter' ),
			Capabilities::ACCESS,
			'fhb-tickets',
			array( $this, 'home' ),
			'dashicons-tickets-alt',
			26
		);
	}

	/**
	 * Runs before admin-header.php, so the redirect still has a chance to send headers.
	 * The page callback itself is too late: WordPress has already printed the admin chrome.
	 */
	public function redirect_home(): void {
		if ( current_user_can( 'edit_fhb_events' ) || current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=fhb_event' ) );
			exit;
		}
		if ( Capabilities::can_checkin() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=fhb-checkin' ) );
			exit;
		}
	}

	public function home(): void {
		if ( ! Capabilities::can( Capabilities::ACCESS ) && ! Capabilities::can_checkin() ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Billetter', 'forsamlingshus-billetter' ) . '</h1>';
		echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=fhb_event' ) ) . '">' . esc_html__( 'Arrangementer', 'forsamlingshus-billetter' ) . '</a></p>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=fhb-checkin' ) ) . '">' . esc_html__( 'Indtjekning', 'forsamlingshus-billetter' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * @param array<string, string> $links
	 * @return array<string, string>
	 */
	public function links( array $links ): array {
		$url = admin_url( 'admin.php?page=fhb-settings' );
		$links['fhb_settings'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Indstillinger', 'forsamlingshus-billetter' ) . '</a>';

		return $links;
	}
}
