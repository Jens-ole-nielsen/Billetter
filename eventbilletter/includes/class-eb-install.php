<?php
/**
 * Plugin activation / deactivation.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Install
 */
class EB_Install {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		EB_Post_Types::register();
		flush_rewrite_rules();

		if ( ! get_option( 'eb_settings' ) ) {
			update_option(
				'eb_settings',
				array(
					'stripe_mode'           => 'test',
					'stripe_test_secret'    => '',
					'stripe_test_publishable' => '',
					'stripe_live_secret'    => '',
					'stripe_live_publishable' => '',
					'stripe_webhook_secret' => '',
					'currency'              => 'dkk',
					'from_email'            => get_option( 'admin_email' ),
					'from_name'             => get_bloginfo( 'name' ),
					'success_page_id'       => 0,
					'cancel_page_id'        => 0,
				)
			);
		}

		update_option( 'eb_version', EB_VERSION );
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
