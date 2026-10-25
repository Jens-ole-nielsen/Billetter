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

	/**
	 * Release remembered before this request's update check. WordPress rebuilds
	 * the plugin list during the update click, and a failed GitHub answer must
	 * not throw away the release the screen already offered.
	 *
	 * @var object|null
	 */
	private $snapshot = null;

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
		add_action( 'admin_init', array( $this, 'remember_offer' ), 0 );
		add_action( 'admin_init', array( $this, 'restore_offer' ), 11 );
		add_filter( 'update_plugins_github.com', array( $this, 'supply_update' ), 10, 4 );
		add_filter( 'site_transient_update_plugins', array( $this, 'keep_offer' ), 20 );
		add_filter(
			'puc_check_now-forsamlingshus-billetter',
			function ( $should_check, $last_check ) {
				$action = function_exists( 'current_action' ) ? (string) current_action() : '';

				return self::should_refresh( (bool) $should_check, (int) $last_check, time(), $action, self::is_update_request() );
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
	 *
	 * The update click itself must not check again. That request is what installs
	 * the release already on screen. A failed answer would delete it, and WordPress
	 * would then say the plugin is already current.
	 */
	public static function should_refresh( bool $due, int $last_check, int $now, string $action, bool $installing = false ): bool {
		if ( $installing ) {
			return false;
		}
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

	/**
	 * @return array{version:string,package:string}|null
	 */
	public static function offer_from_state( mixed $state ): ?array {
		if ( ! is_object( $state ) || ! isset( $state->update ) || ! is_object( $state->update ) ) {
			return null;
		}
		$version = trim( (string) ( $state->update->version ?? '' ) );
		$package = trim( (string) ( $state->update->download_url ?? '' ) );
		if ( '' === $version || '' === $package ) {
			return null;
		}

		return array(
			'version' => $version,
			'package' => $package,
		);
	}

	public static function offer_is_newer( string $offer, string $installed ): bool {
		return '' !== $offer && version_compare( $offer, $installed, '>' );
	}

	public function remember_offer(): void {
		$state = get_site_option( 'fhb_update_check' );
		if ( is_object( $state ) ) {
			$this->snapshot = clone $state;
			if ( isset( $this->snapshot->update ) && is_object( $this->snapshot->update ) ) {
				$this->snapshot->update = clone $this->snapshot->update;
			}
		}
	}

	/**
	 * A check that gets no answer deletes the cached release. Put it back when
	 * it is still newer than the installed plugin.
	 */
	public function restore_offer(): void {
		$offer = self::offer_from_state( $this->snapshot );
		if ( null === $offer || ! self::offer_is_newer( $offer['version'], $this->installed_version() ) ) {
			return;
		}
		$state = get_site_option( 'fhb_update_check' );
		if ( null !== self::offer_from_state( $state ) ) {
			return;
		}
		if ( ! is_object( $state ) ) {
			$state = new \stdClass();
		}
		$state->update = $this->snapshot->update;
		if ( isset( $this->snapshot->updateClass ) ) {
			$state->updateClass = $this->snapshot->updateClass;
		}
		if ( isset( $this->snapshot->updateBaseClass ) ) {
			$state->updateBaseClass = $this->snapshot->updateBaseClass;
		}
		update_site_option( 'fhb_update_check', $state );
	}

	/**
	 * WordPress asks this while rebuilding the update list, because the plugin
	 * header has an Update URI on github.com. The rebuilt list is what the
	 * upgrader reads.
	 *
	 * @param mixed                $update
	 * @param array<string,mixed>  $plugin_data
	 * @param string[]             $locales
	 * @return mixed
	 */
	public function supply_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $plugin_data, $locales );
		if ( self::plugin_file() !== $plugin_file ) {
			return $update;
		}
		$offer = $this->current_offer();
		if ( null === $offer ) {
			return $update;
		}

		return array(
			'slug' => 'forsamlingshus-billetter',
			'version' => $offer['version'],
			'package' => $offer['package'],
			'url' => 'https://github.com/Jens-ole-nielsen/Billetter',
		);
	}

	/**
	 * @param mixed $updates
	 * @return mixed
	 */
	public function keep_offer( $updates ) {
		$offer = $this->current_offer();
		if ( null === $offer || ! self::offer_is_newer( $offer['version'], $this->installed_version() ) ) {
			return $updates;
		}
		if ( ! is_object( $updates ) ) {
			$updates = new \stdClass();
		}
		if ( ! isset( $updates->response ) || ! is_array( $updates->response ) ) {
			$updates->response = array();
		}
		$file = self::plugin_file();
		$existing = $updates->response[ $file ] ?? null;
		if ( is_object( $existing ) && ! empty( $existing->package ) ) {
			return $updates;
		}
		$updates->response[ $file ] = (object) array(
			'id' => 'https://github.com/Jens-ole-nielsen/Billetter',
			'slug' => 'forsamlingshus-billetter',
			'plugin' => $file,
			'new_version' => $offer['version'],
			'url' => 'https://github.com/Jens-ole-nielsen/Billetter',
			'package' => $offer['package'],
		);
		if ( isset( $updates->no_update ) && is_array( $updates->no_update ) ) {
			unset( $updates->no_update[ $file ] );
		}

		return $updates;
	}

	/**
	 * @return array{version:string,package:string}|null
	 */
	private function current_offer(): ?array {
		$live = self::offer_from_state( get_site_option( 'fhb_update_check' ) );
		if ( null !== $live ) {
			return $live;
		}

		return self::offer_from_state( $this->snapshot );
	}

	private function installed_version(): string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			if ( defined( 'ABSPATH' ) && is_file( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}
		if ( ! function_exists( 'get_plugin_data' ) || ! defined( 'FHB_FILE' ) ) {
			return '0';
		}
		$data = get_plugin_data( FHB_FILE, false, false );

		return (string) ( $data['Version'] ?? '0' );
	}

	private static function plugin_file(): string {
		if ( defined( 'FHB_FILE' ) && function_exists( 'plugin_basename' ) ) {
			return plugin_basename( FHB_FILE );
		}

		return 'forsamlingshus-billetter/forsamlingshus-billetter.php';
	}

	private static function is_update_request(): bool {
		if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) {
			return false;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) : '';

		return 'update-plugin' === $action;
	}
}
