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

	/**
	 * Hourly cron. The Plugins and Updates screens recheck a "no update" result
	 * after two minutes, so a new release does not stay hidden for hours.
	 */
	public const CHECK_PERIOD_HOURS = 1;

	public const SCREEN_REFRESH_SECONDS = 120;

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
			'https://github.com/Jens-ole-nielsen/Billetter',
			FHB_FILE,
			'forsamlingshus-billetter',
			self::CHECK_PERIOD_HOURS,
			'fhb_update_check'
		);
		add_filter(
			'puc_check_now-forsamlingshus-billetter',
			static function ( $should_check, $last_check ) {
				$action = function_exists( 'current_action' ) ? (string) current_action() : '';

				return self::should_refresh( (bool) $should_check, (int) $last_check, time(), $action );
			},
			10,
			2
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

		$token = self::token_for_auth( $this->settings->github_token() );
		if ( '' !== $token && method_exists( $checker, 'setAuthentication' ) ) {
			$checker->setAuthentication( $token );
		}
	}

	/**
	 * A blank token stays blank. The public repo is read without Authorization.
	 */
	public static function token_for_auth( string $token ): string {
		return trim( $token );
	}

	/**
	 * Keep a scheduled check, and also refresh when someone opens Plugins or Updates
	 * and the last "no update" answer is more than two minutes old.
	 */
	public static function should_refresh( bool $due, int $last_check, int $now, string $action ): bool {
		if ( $due ) {
			return true;
		}
		$screens = array( 'load-plugins.php', 'load-update-core.php', 'load-update.php' );
		if ( ! in_array( $action, $screens, true ) ) {
			return false;
		}
		if ( $last_check < 1 ) {
			return true;
		}

		return ( $now - $last_check ) >= self::SCREEN_REFRESH_SECONDS;
	}
}
