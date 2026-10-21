<?php
/**
 * Plugin Name:       Eventbilletter
 * Plugin URI:        https://github.com/example/eventbilletter
 * Description:       Sælg billetter til events med Stripe — uden WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Eventbilletter
 * License:           GPL-2.0-or-later
 * Text Domain:       eventbilletter
 * Domain Path:       /languages
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

define( 'EB_VERSION', '1.0.0' );
define( 'EB_PLUGIN_FILE', __FILE__ );
define( 'EB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once EB_PLUGIN_DIR . 'includes/class-eb-autoloader.php';
EB_Autoloader::register();

/**
 * Main plugin instance.
 *
 * @return EB_Plugin
 */
function eventbilletter() {
	return EB_Plugin::instance();
}

register_activation_hook( __FILE__, array( 'EB_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EB_Install', 'deactivate' ) );

add_action( 'plugins_loaded', 'eventbilletter' );
