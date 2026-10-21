<?php
/**
 * Core plugin bootstrap.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Plugin
 */
class EB_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var EB_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return EB_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function init_hooks() {
		add_action( 'init', array( 'EB_Post_Types', 'register' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Boot subsystems.
	 */
	private function init_components() {
		new EB_Meta_Boxes();
		new EB_Shortcodes();
		new EB_Checkout();
		new EB_Webhooks();
		new EB_Public();

		if ( is_admin() ) {
			new EB_Admin();
			new EB_Settings();
			new EB_Orders_Admin();
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'eventbilletter', false, dirname( EB_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = '' ) {
		$settings = get_option( 'eb_settings', array() );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update settings (partial merge).
	 *
	 * @param array $values Values to merge.
	 */
	public function update_settings( array $values ) {
		$settings = get_option( 'eb_settings', array() );
		update_option( 'eb_settings', array_merge( $settings, $values ) );
	}
}
