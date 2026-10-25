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
		add_filter( 'plugin_action_links_' . plugin_basename( FHB_FILE ), array( $this, 'links' ) );
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

	public function home(): void {
		if ( ! Capabilities::can( Capabilities::ACCESS ) && ! Capabilities::can_checkin() ) {
			wp_die( esc_html__( 'Du har ikke adgang.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
		if ( current_user_can( 'edit_fhb_events' ) || current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=fhb_event' ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=fhb-checkin' ) );
		exit;
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
