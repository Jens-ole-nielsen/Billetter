<?php
/**
 * Admin assets and notices.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Admin
 */
class EB_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'stripe_notice' ) );
	}

	/**
	 * Enqueue admin scripts/styles on plugin screens.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$relevant = in_array( $screen->post_type, array( 'eb_event', 'eb_order' ), true )
			|| false !== strpos( $hook, 'eventbilletter' );

		if ( ! $relevant ) {
			return;
		}

		wp_enqueue_style(
			'eb-admin',
			EB_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			EB_VERSION
		);

		wp_enqueue_script(
			'eb-admin',
			EB_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			EB_VERSION,
			true
		);
	}

	/**
	 * Warn if Stripe is not configured.
	 */
	public function stripe_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ( 'eb_event' !== $screen->post_type && false === strpos( $screen->id, 'eventbilletter' ) ) ) {
			return;
		}

		if ( EB_Stripe::is_configured() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Eventbilletter: Stripe er endnu ikke konfigureret.', 'eventbilletter' ),
			esc_url( admin_url( 'edit.php?post_type=eb_event&page=eventbilletter-settings' ) ),
			esc_html__( 'Gå til indstillinger', 'eventbilletter' )
		);
	}
}
