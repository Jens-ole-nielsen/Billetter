<?php
/**
 * Event field access.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the event custom post type into a plain array.
 */
final class Events {

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'fhb_event' !== $post->post_type ) {
			return null;
		}

		$time = (string) get_post_meta( $id, '_fhb_time', true );
		if ( 1 === preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ) {
			$time = substr( $time, 0, 5 );
		}

		return array(
			'id' => $id,
			'title' => get_the_title( $post ),
			'description' => (string) $post->post_content,
			'status' => (string) $post->post_status,
			'capacity' => (int) get_post_meta( $id, '_fhb_capacity', true ),
			'date' => (string) get_post_meta( $id, '_fhb_date', true ),
			'time' => $time,
			'sales_start' => (string) get_post_meta( $id, '_fhb_sales_start', true ),
			'price_ore' => (int) get_post_meta( $id, '_fhb_price_ore', true ),
			'invoice' => '1' === (string) get_post_meta( $id, '_fhb_invoice', true ),
			'poster_id' => (int) get_post_meta( $id, '_fhb_poster_id', true ),
			'ticket_image_id' => (int) get_post_meta( $id, '_fhb_ticket_image_id', true ),
			'show_remaining' => self::shows_remaining_count( (string) get_post_meta( $id, '_fhb_show_remaining', true ) ),
			'sales_page_id' => (int) get_post_meta( $id, '_fhb_sales_page_id', true ),
		);
	}

	/**
	 * Missing or any value other than 0 keeps the public count, so existing events stay unchanged.
	 */
	public static function shows_remaining_count( string $stored ): bool {
		return '0' !== $stored;
	}

	/**
	 * @return list<\WP_Post>
	 */
	public static function choices(): array {
		$posts = get_posts(
			array(
				'post_type' => 'fhb_event',
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 200,
				'orderby' => 'meta_value',
				'meta_key' => '_fhb_date',
				'order' => 'DESC',
			)
		);

		return is_array( $posts ) ? $posts : array();
	}
}
