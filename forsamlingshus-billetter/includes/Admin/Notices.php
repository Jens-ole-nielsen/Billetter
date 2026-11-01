<?php
/**
 * One-shot admin notices.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Stores a notice for the current user until the next admin screen.
 */
final class Notices {

	public static function flash( string $type, string $text ): void {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return;
		}
		$key = 'fhb_notice_' . $user_id;
		$items = get_transient( $key );
		if ( ! is_array( $items ) ) {
			$items = array();
		}
		$items[] = array(
			'type' => 'error' === $type ? 'error' : 'success',
			'text' => $text,
		);
		set_transient( $key, $items, MINUTE_IN_SECONDS );
	}

	public function hooks(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function render(): void {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return;
		}
		$key = 'fhb_notice_' . $user_id;
		$items = get_transient( $key );
		if ( ! is_array( $items ) || array() === $items ) {
			return;
		}
		delete_transient( $key );
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = isset( $item['type'] ) && 'error' === $item['type'] ? 'error' : 'success';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( (string) ( $item['text'] ?? '' ) ) . '</p></div>';
		}
	}
}
