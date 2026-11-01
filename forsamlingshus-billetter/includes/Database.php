<?php
/**
 * Custom tables.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Orders, tickets and the stock row used to keep the last seats from being oversold.
 */
final class Database {

	public static function orders_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'fhb_orders';
	}

	public static function tickets_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'fhb_tickets';
	}

	public static function stock_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'fhb_stock';
	}

	public static function coupons_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'fhb_coupons';
	}

	public function install_if_needed(): void {
		if ( get_option( 'fhb_db_version' ) === FHB_DB_VERSION ) {
			return;
		}
		self::install();
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$orders = self::orders_table();
		$tickets = self::tickets_table();
		$stock = self::stock_table();
		$coupons = self::coupons_table();

		$sql = "CREATE TABLE {$orders} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 event_id bigint(20) unsigned NOT NULL DEFAULT 0,
 buyer_name varchar(190) NOT NULL DEFAULT '',
 buyer_email varchar(190) NOT NULL DEFAULT '',
 buyer_phone varchar(40) NOT NULL DEFAULT '',
 quantity int(10) unsigned NOT NULL DEFAULT 0,
 amount_ore bigint(20) NOT NULL DEFAULT 0,
 discount_ore bigint(20) NOT NULL DEFAULT 0,
 coupon_code varchar(40) NOT NULL DEFAULT '',
 currency char(3) NOT NULL DEFAULT 'DKK',
 status varchar(20) NOT NULL DEFAULT 'pending',
 remark text NOT NULL,
 payment_method varchar(20) NOT NULL DEFAULT '',
 stripe_session_id varchar(255) NOT NULL DEFAULT '',
 stripe_payment_intent varchar(255) NOT NULL DEFAULT '',
 refunded_ore bigint(20) NOT NULL DEFAULT 0,
 stripe_refund_ids varchar(1000) NOT NULL DEFAULT '',
 public_token varchar(64) NOT NULL DEFAULT '',
 expires_at datetime DEFAULT NULL,
 paid_at datetime DEFAULT NULL,
 tickets_sent_at datetime DEFAULT NULL,
 failure_reason varchar(255) NOT NULL DEFAULT '',
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY  (id),
 UNIQUE KEY public_token (public_token),
 KEY event_status (event_id, status),
 KEY buyer_email (buyer_email),
 KEY stripe_session_id (stripe_session_id),
 KEY coupon_code (coupon_code)
) {$charset};
CREATE TABLE {$tickets} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 order_id bigint(20) unsigned NOT NULL DEFAULT 0,
 event_id bigint(20) unsigned NOT NULL DEFAULT 0,
 token varchar(64) NOT NULL DEFAULT '',
 sequence_no int(10) unsigned NOT NULL DEFAULT 0,
 status varchar(20) NOT NULL DEFAULT 'valid',
 checked_in_at datetime DEFAULT NULL,
 checked_in_by bigint(20) unsigned NOT NULL DEFAULT 0,
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY  (id),
 UNIQUE KEY token (token),
 KEY order_sequence (order_id, sequence_no),
 KEY event_id (event_id),
 KEY status (status)
) {$charset};
CREATE TABLE {$stock} (
 event_id bigint(20) unsigned NOT NULL,
 capacity int(10) unsigned NOT NULL DEFAULT 0,
 reserved int(10) unsigned NOT NULL DEFAULT 0,
 sold int(10) unsigned NOT NULL DEFAULT 0,
 PRIMARY KEY  (event_id)
) {$charset};
CREATE TABLE {$coupons} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 code varchar(40) NOT NULL DEFAULT '',
 type varchar(20) NOT NULL DEFAULT '',
 percent tinyint(3) unsigned NOT NULL DEFAULT 0,
 free_tickets int(10) unsigned NOT NULL DEFAULT 0,
 event_id bigint(20) unsigned NOT NULL DEFAULT 0,
 max_uses int(10) unsigned NOT NULL DEFAULT 0,
 active tinyint(1) NOT NULL DEFAULT 1,
 expires_on date DEFAULT NULL,
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY  (id),
 UNIQUE KEY code (code),
 KEY event_id (event_id)
) {$charset};";

		dbDelta( $sql );

		$wpdb->hide_errors();
		foreach ( array( $orders, $tickets, $stock, $coupons ) as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are plugin constants.
			$wpdb->query( "ALTER TABLE {$table} ENGINE=InnoDB" );
		}
		$wpdb->show_errors();

		update_option( 'fhb_db_version', FHB_DB_VERSION, false );
	}
}
