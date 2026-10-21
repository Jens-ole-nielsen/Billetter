<?php
/**
 * Uninstall Eventbilletter.
 *
 * @package Eventbilletter
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'eb_settings' );
delete_option( 'eb_version' );

// Optionally remove CPT content — left intact by default so data isn't lost.
// Uncomment to purge events and orders on uninstall:
/*
$posts = get_posts(
	array(
		'post_type'      => array( 'eb_event', 'eb_order' ),
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);
foreach ( $posts as $post_id ) {
	wp_delete_post( $post_id, true );
}
*/
