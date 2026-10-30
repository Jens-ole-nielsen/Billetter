<?php
/**
 * Class loader for plugin code. Third-party code uses Composer.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Forsamlingshus\\Billetter\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_file( $path ) ) {
			require_once $path;
		}
	}
);
