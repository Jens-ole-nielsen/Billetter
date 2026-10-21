<?php
/**
 * Simple PSR-4-ish autoloader for Eventbilletter classes.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Autoloader
 */
class EB_Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload EB_* classes from includes/, admin/ and public/.
	 *
	 * @param string $class Class name.
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, 'EB_' ) ) {
			return;
		}

		$file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

		$paths = array(
			EB_PLUGIN_DIR . 'includes/' . $file,
			EB_PLUGIN_DIR . 'admin/' . $file,
			EB_PLUGIN_DIR . 'public/' . $file,
		);

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
