<?php
/**
 * Uninstall.
 *
 * Orders, tickets and events are kept unless FHB_REMOVE_DATA is defined as true
 * in wp-config.php before the plugin is deleted.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'FHB_REMOVE_DATA' ) || true !== FHB_REMOVE_DATA ) {
	return;
}

global $wpdb;

require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Capabilities.php';

use Forsamlingshus\Billetter\Capabilities;
use Forsamlingshus\Billetter\Database;

$wpdb->query( 'DROP TABLE IF EXISTS ' . Database::tickets_table() );
$wpdb->query( 'DROP TABLE IF EXISTS ' . Database::orders_table() );
$wpdb->query( 'DROP TABLE IF EXISTS ' . Database::stock_table() );

delete_option( 'fhb_db_version' );
delete_option( 'fhb_settings' );
delete_option( 'fhb_design' );
delete_option( 'fhb_caps_version' );

Capabilities::remove();

$timestamp = wp_next_scheduled( 'fhb_expire_pending' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'fhb_expire_pending' );
}

$page_ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_fhb_event_id'" );
if ( is_array( $page_ids ) ) {
	foreach ( $page_ids as $page_id ) {
		wp_delete_post( (int) $page_id, true );
	}
}

$event_ids = get_posts(
	array(
		'post_type' => 'fhb_event',
		'post_status' => 'any',
		'posts_per_page' => -1,
		'fields' => 'ids',
	)
);
if ( is_array( $event_ids ) ) {
	foreach ( $event_ids as $event_id ) {
		wp_delete_post( (int) $event_id, true );
	}
}
