<?php
/**
 * Plugin updates from GitHub releases.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

use YahnisElsts\PluginUpdateChecker\v5p7\PucFactory;
use YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api;

defined( 'ABSPATH' ) || exit;

/**
 * A release is used only when it contains the installable zip asset.
 */
final class Updates {

	public function __construct( private Settings $settings ) {
	}

	public function hooks(): void {
		$this->register();
	}

	public function register(): void {
		if ( ! class_exists( PucFactory::class ) || ! class_exists( Api::class ) ) {
			return;
		}

		try {
			$this->attach();
		} catch ( \Throwable $error ) {
			unset( $error );
		}
	}

	private function attach(): void {
		$checker = PucFactory::buildUpdateChecker(
			'https://github.com/Jens-ole-nielsen/Events',
			FHB_FILE,
			'forsamlingshus-billetter',
			12,
			'fhb_update_check'
		);
		if ( method_exists( $checker, 'setBranch' ) ) {
			$checker->setBranch( 'main' );
		}
		if ( method_exists( $checker, 'getVcsApi' ) ) {
			$api = $checker->getVcsApi();
			if ( is_object( $api ) && method_exists( $api, 'enableReleaseAssets' ) ) {
				$api->enableReleaseAssets( '/^forsamlingshus-billetter\.zip$/', Api::REQUIRE_RELEASE_ASSETS );
			}
		}

		$token = $this->settings->github_token();
		if ( '' !== $token && method_exists( $checker, 'setAuthentication' ) ) {
			$checker->setAuthentication( $token );
		}
	}
}
