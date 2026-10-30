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

	public const ASSET_NAME = 'forsamlingshus-billetter.zip';

	public const LATEST_RELEASE_URL = 'https://api.github.com/repos/Jens-ole-nielsen/Billetter/releases/latest';

	/**
	 * Last release we are willing to install. Plugin Update Checker's own option
	 * is cleared by a failed check; this one is not.
	 */
	private const OFFER_OPTION = 'fhb_offered_release';

	/**
	 * Unix time of the last direct read of the latest GitHub release.
	 */
	private const CHECKED_OPTION = 'fhb_release_checked';

	/**
	 * Release remembered before this request's update check. WordPress rebuilds
	 * the plugin list during the update click, and a failed GitHub answer must
	 * not throw away the release the screen already offered.
	 *
	 * @var object|null
	 */
	private $snapshot = null;

	/**
	 * Package copied from the update list before Plugin Update Checker deletes it.
	 * That checker changes the object WordPress has cached, so a later read of the
	 * same option no longer contains the release.
	 *
	 * @var array{version:string,package:string}|null
	 */
	private $seen_offer = null;

	/**
	 * One GitHub read per request. The Plugins screen and the update list both ask.
	 */
	private bool $release_fetched = false;

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
		add_action( 'load-plugins.php', array( $this, 'refresh_release' ), 1 );
		add_action( 'load-update-core.php', array( $this, 'refresh_release' ), 1 );
		add_action( 'load-update.php', array( $this, 'refresh_release' ), 1 );
		add_action( 'admin_init', array( $this, 'remember_offer' ), 0 );
		add_action( 'admin_init', array( $this, 'restore_offer' ), 11 );
		add_filter( 'update_plugins_github.com', array( $this, 'supply_update' ), 10, 4 );
		add_filter( 'site_transient_update_plugins', array( $this, 'note_stored_offer' ), 1 );
		add_filter( 'site_transient_update_plugins', array( $this, 'keep_offer' ), 100 );
		add_filter( 'puc_request_info_options-forsamlingshus-billetter', array( self::class, 'request_options' ) );

		$checker = PucFactory::buildUpdateChecker(
			'https://github.com/Jens-ole-nielsen/Billetter',
			FHB_FILE,
			'forsamlingshus-billetter',
			self::CHECK_PERIOD_HOURS,
			'fhb_update_check'
		);
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

	/**
	 * Plugin Update Checker gives GitHub three seconds and then, if that misses,
	 * reads the version from forsamlingshus-billetter.php at the repository root.
	 * The file lives in the plugin directory, so a missed release becomes "no update"
	 * and the previous offer is deleted. Ask for the latest release directly.
	 *
	 * @param mixed $options
	 * @return mixed
	 */
	public static function request_options( mixed $options ): mixed {
		if ( ! is_array( $options ) ) {
			return $options;
		}
		$options['timeout'] = 15;
		if ( ! isset( $options['headers'] ) || ! is_array( $options['headers'] ) ) {
			$options['headers'] = array();
		}
		if ( empty( $options['headers']['User-Agent'] ) ) {
			$options['headers']['User-Agent'] = 'forsamlingshus-billetter';
		}

		return $options;
	}

	/**
	 * A GitHub release object, or the JSON text of one. The zip asset is required.
	 * Drafts, pre-releases, and a release that is not newer are left to the caller.
	 *
	 * @return array{version:string,package:string}|null
	 */
	public static function offer_from_release( mixed $release ): ?array {
		if ( is_string( $release ) ) {
			$release = json_decode( $release );
		}
		if ( ! is_object( $release ) ) {
			return null;
		}
		if ( ! empty( $release->draft ) || ! empty( $release->prerelease ) ) {
			return null;
		}
		$version = ltrim( trim( (string) ( $release->tag_name ?? '' ) ), 'vV' );
		if ( '' === $version || 1 !== preg_match( '/^\d/', $version ) ) {
			return null;
		}
		$assets = $release->assets ?? null;
		if ( ! is_array( $assets ) ) {
			return null;
		}
		foreach ( $assets as $asset ) {
			if ( ! is_object( $asset ) || self::ASSET_NAME !== (string) ( $asset->name ?? '' ) ) {
				continue;
			}
			$package = trim( (string) ( $asset->browser_download_url ?? '' ) );
			if ( ! str_starts_with( $package, 'https://' ) ) {
				return null;
			}

			return array(
				'version' => $version,
				'package' => $package,
			);
		}

		return null;
	}

	/**
	 * Read the latest public release. A stored token that GitHub rejects is ignored,
	 * and a failed read leaves the previously saved offer in place.
	 *
	 * @return array{version:string,package:string}|null
	 */
	public static function fetch_latest_offer( string $token ): ?array {
		if ( ! function_exists( 'wp_remote_get' ) || ! function_exists( 'wp_remote_retrieve_response_code' ) || ! function_exists( 'wp_remote_retrieve_body' ) ) {
			return null;
		}
		$response = self::github_get( $token );
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $code && '' !== $token ) {
			$response = self::github_get( '' );
			$code = (int) wp_remote_retrieve_response_code( $response );
		}
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return null;
		}
		if ( 200 !== $code ) {
			return null;
		}

		return self::offer_from_release( (string) wp_remote_retrieve_body( $response ) );
	}

	/**
	 * @return mixed
	 */
	private static function github_get( string $token ) {
		$headers = array(
			'Accept' => 'application/vnd.github+json',
			'User-Agent' => 'forsamlingshus-billetter',
		);
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		return wp_remote_get(
			self::LATEST_RELEASE_URL,
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);
	}

	/**
	 * Plugins, Updates, and WordPress' own update list call this. The install
	 * click does not: that request must keep the release already on screen.
	 */
	public function refresh_release(): void {
		if ( $this->release_fetched || self::is_update_request() ) {
			return;
		}
		$this->release_fetched = true;
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}
		$last = (int) get_option( self::CHECKED_OPTION, 0 );
		if ( $last > 0 && ( time() - $last ) < self::SCREEN_REFRESH_SECONDS ) {
			return;
		}
		update_option( self::CHECKED_OPTION, time(), false );
		$offer = self::fetch_latest_offer( self::token_for_auth( $this->settings->github_token() ) );
		if ( null === $offer || ! self::offer_is_newer( $offer['version'], $this->installed_version() ) ) {
			return;
		}
		$this->store_offer( $offer );
	}

	/**
	 * The release to install. A failed GitHub check deletes Plugin Update Checker's
	 * copy, and that checker then removes the release WordPress already stored.
	 * Keep the newest package that is still ahead of the installed plugin.
	 *
	 * @param list<array{version:string,package:string}|null> $candidates
	 * @return array{version:string,package:string}|null
	 */
	public static function choose_offer( array $candidates, string $installed ): ?array {
		$best = null;
		foreach ( $candidates as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}
			$version = trim( (string) ( $candidate['version'] ?? '' ) );
			$package = trim( (string) ( $candidate['package'] ?? '' ) );
			if ( ! self::offer_is_newer( $version, $installed ) || '' === $package ) {
				continue;
			}
			if ( null === $best || version_compare( $version, $best['version'], '>' ) ) {
				$best = array(
					'version' => $version,
					'package' => $package,
				);
			}
		}

		return $best;
	}

	/**
	 * Copy the release before Plugin Update Checker removes it from the list.
	 * The copy is plain text, so a later edit of the cached object cannot erase it.
	 *
	 * @param mixed $updates
	 * @return mixed
	 */
	public function note_stored_offer( $updates ) {
		$offer = self::offer_from_transient_value( $updates );
		if ( null === $offer || ! self::offer_is_newer( $offer['version'], $this->installed_version() ) ) {
			return $updates;
		}
		$this->seen_offer = $offer;
		$this->store_offer( $offer );

		return $updates;
	}

	/**
	 * @param mixed $updates
	 * @return array{version:string,package:string}|null
	 */
	public static function offer_from_transient_value( mixed $updates ): ?array {
		if ( ! is_object( $updates ) || ! isset( $updates->response ) || ! is_array( $updates->response ) ) {
			return null;
		}
		$item = $updates->response[ self::plugin_file() ] ?? null;
		if ( ! is_object( $item ) ) {
			return null;
		}
		$version = trim( (string) ( $item->new_version ?? $item->version ?? '' ) );
		$package = trim( (string) ( $item->package ?? '' ) );
		if ( '' === $version || '' === $package ) {
			return null;
		}

		return array(
			'version' => $version,
			'package' => $package,
		);
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
		$this->refresh_release();
		$offer = $this->current_offer();
		if ( null === $offer ) {
			return $update;
		}

		return array(
			'slug' => 'forsamlingshus-billetter',
			'version' => $offer['version'],
			'new_version' => $offer['version'],
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
		$installed = $this->installed_version();
		$best = self::choose_offer(
			array(
				self::offer_from_state( get_site_option( 'fhb_update_check' ) ),
				self::offer_from_state( $this->snapshot ),
				$this->seen_offer,
				$this->saved_offer(),
				$this->offer_from_stored_transient(),
			),
			$installed
		);
		if ( null === $best ) {
			$saved = $this->saved_offer();
			if ( null !== $saved && ! self::offer_is_newer( $saved['version'], $installed ) ) {
				delete_option( self::OFFER_OPTION );
			}

			return null;
		}
		$this->store_offer( $best );

		return $best;
	}

	/**
	 * @return array{version:string,package:string}|null
	 */
	private function saved_offer(): ?array {
		$saved = get_option( self::OFFER_OPTION );
		if ( ! is_array( $saved ) ) {
			return null;
		}
		$version = trim( (string) ( $saved['version'] ?? '' ) );
		$package = trim( (string) ( $saved['package'] ?? '' ) );
		if ( '' === $version || '' === $package ) {
			return null;
		}

		return array(
			'version' => $version,
			'package' => $package,
		);
	}

	/**
	 * @param array{version:string,package:string} $offer
	 */
	private function store_offer( array $offer ): void {
		$saved = $this->saved_offer();
		if ( null !== $saved && $saved['version'] === $offer['version'] && $saved['package'] === $offer['package'] ) {
			return;
		}
		update_option( self::OFFER_OPTION, $offer, false );
	}

	/**
	 * WordPress may already have saved the release. Reading the option avoids
	 * the filter that Plugin Update Checker uses to remove it.
	 *
	 * @return array{version:string,package:string}|null
	 */
	private function offer_from_stored_transient(): ?array {
		if ( ! function_exists( 'get_site_option' ) ) {
			return null;
		}

		return self::offer_from_transient_value( get_site_option( '_site_transient_update_plugins' ) );
	}

	private function installed_version(): string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			if ( defined( 'ABSPATH' ) && is_file( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}
		if ( ! function_exists( 'get_plugin_data' ) || ! defined( 'FHB_FILE' ) ) {
			return defined( 'FHB_VERSION' ) ? FHB_VERSION : '0';
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
