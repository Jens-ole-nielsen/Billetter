<?php
/**
 * Plugin Name: Forsamlingshus Billetter
 * Plugin URI: https://github.com/Jens-ole-nielsen/Billetter
 * Description: Sælg billetter til arrangementer i forsamlingshuset. Stripe Checkout og faktura efter aftale. Ingen WooCommerce.
 * Version: 1.7.8
 * Update URI: https://github.com/Jens-ole-nielsen/Billetter
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Forsamlingshus Billetter
 * Text Domain: forsamlingshus-billetter
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'FHB_VERSION', '1.7.8' );
define( 'FHB_DB_VERSION', '1.2.0' );
define( 'FHB_FILE', __FILE__ );
define( 'FHB_PATH', plugin_dir_path( __FILE__ ) );
define( 'FHB_URL', plugin_dir_url( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	return;
}

if ( ! is_file( FHB_PATH . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Forsamlingshus Billetter mangler mappen vendor. Installer hele plugin-mappen.', 'forsamlingshus-billetter' ) . '</p></div>';
		}
	);
	return;
}

require_once FHB_PATH . 'vendor/autoload.php';
require_once FHB_PATH . 'includes/Autoload.php';

register_activation_hook( FHB_FILE, array( Forsamlingshus\Billetter\Activator::class, 'activate' ) );
register_deactivation_hook( FHB_FILE, array( Forsamlingshus\Billetter\Activator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		( new Forsamlingshus\Billetter\Plugin() )->boot();
	},
	20
);
