<?php
/**
 * Roles and capabilities.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Administrators manage the hall. Door staff can only check tickets in.
 */
final class Capabilities {

	public const ACCESS = 'fhb_access';

	public const ORDERS = 'fhb_manage_orders';

	public const CHECKIN = 'fhb_checkin';

	public const EMAIL = 'fhb_send_email';

	public const SETTINGS = 'fhb_manage_settings';

	public const DESIGN = 'fhb_design_tickets';

	public const VERSION = '1';

	/**
	 * @return list<string>
	 */
	public static function admin_caps(): array {
		return array_merge(
			array(
				self::ACCESS,
				self::ORDERS,
				self::CHECKIN,
				self::EMAIL,
				self::SETTINGS,
				self::DESIGN,
			),
			self::event_caps()
		);
	}

	/**
	 * Caps mapped from capability_type fhb_event.
	 *
	 * @return list<string>
	 */
	public static function event_caps(): array {
		return array(
			'edit_fhb_event',
			'read_fhb_event',
			'delete_fhb_event',
			'edit_fhb_events',
			'edit_others_fhb_events',
			'publish_fhb_events',
			'read_private_fhb_events',
			'delete_fhb_events',
			'delete_private_fhb_events',
			'delete_published_fhb_events',
			'delete_others_fhb_events',
			'edit_private_fhb_events',
			'edit_published_fhb_events',
			'create_fhb_events',
		);
	}

	public static function ensure(): void {
		if ( self::VERSION === get_option( 'fhb_caps_version' ) ) {
			return;
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::admin_caps() as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		$door = get_role( 'fhb_door' );
		if ( ! $door ) {
			add_role(
				'fhb_door',
				'Dørvagt',
				array(
					'read' => true,
					self::ACCESS => true,
					self::CHECKIN => true,
				)
			);
		} else {
			$door->add_cap( 'read' );
			$door->add_cap( self::ACCESS );
			$door->add_cap( self::CHECKIN );
		}

		update_option( 'fhb_caps_version', self::VERSION, false );
	}

	public static function can( string $cap ): bool {
		return current_user_can( $cap ) || current_user_can( 'manage_options' );
	}

	public static function can_checkin(): bool {
		return current_user_can( self::CHECKIN ) || current_user_can( 'manage_options' );
	}

	public static function remove(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::admin_caps() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
		remove_role( 'fhb_door' );
		delete_option( 'fhb_caps_version' );
	}
}
