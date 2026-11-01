<?php
/**
 * Tiny template loader.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a PHP template from the plugin templates directory.
 */
final class View {

	/**
	 * @param array<string, mixed> $data
	 */
	public static function render( string $name, array $data = array() ): string {
		$file = FHB_PATH . 'templates/' . $name . '.php';
		if ( ! is_file( $file ) ) {
			return '';
		}

		ob_start();
		( static function () use ( $file, $data ): void {
			extract( $data, EXTR_SKIP );
			include $file;
		} )();

		return (string) ob_get_clean();
	}
}
