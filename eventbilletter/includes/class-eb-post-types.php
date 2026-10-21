<?php
/**
 * Custom post types: Events and Orders.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Post_Types
 */
class EB_Post_Types {

	/**
	 * Register CPTs.
	 */
	public static function register() {
		self::register_events();
		self::register_orders();
	}

	/**
	 * Events post type.
	 */
	private static function register_events() {
		$labels = array(
			'name'               => __( 'Events', 'eventbilletter' ),
			'singular_name'      => __( 'Event', 'eventbilletter' ),
			'add_new'            => __( 'Tilføj nyt', 'eventbilletter' ),
			'add_new_item'       => __( 'Tilføj nyt event', 'eventbilletter' ),
			'edit_item'          => __( 'Rediger event', 'eventbilletter' ),
			'new_item'           => __( 'Nyt event', 'eventbilletter' ),
			'view_item'          => __( 'Se event', 'eventbilletter' ),
			'search_items'       => __( 'Søg events', 'eventbilletter' ),
			'not_found'          => __( 'Ingen events fundet', 'eventbilletter' ),
			'not_found_in_trash' => __( 'Ingen events i papirkurven', 'eventbilletter' ),
			'menu_name'          => __( 'Events', 'eventbilletter' ),
			'all_items'          => __( 'Alle events', 'eventbilletter' ),
		);

		register_post_type(
			'eb_event',
			array(
				'labels'              => $labels,
				'public'              => true,
				'has_archive'         => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-tickets-alt',
				'menu_position'       => 25,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'rewrite'             => array( 'slug' => 'events' ),
				'capability_type'     => 'post',
			)
		);
	}

	/**
	 * Orders post type (admin-only).
	 */
	private static function register_orders() {
		$labels = array(
			'name'               => __( 'Ordrer', 'eventbilletter' ),
			'singular_name'      => __( 'Ordre', 'eventbilletter' ),
			'edit_item'          => __( 'Se ordre', 'eventbilletter' ),
			'search_items'       => __( 'Søg ordrer', 'eventbilletter' ),
			'not_found'          => __( 'Ingen ordrer fundet', 'eventbilletter' ),
			'not_found_in_trash' => __( 'Ingen ordrer i papirkurven', 'eventbilletter' ),
			'menu_name'          => __( 'Ordrer', 'eventbilletter' ),
			'all_items'          => __( 'Alle ordrer', 'eventbilletter' ),
		);

		register_post_type(
			'eb_order',
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=eb_event',
				'show_in_rest'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'capabilities'        => array(
					'create_posts' => 'do_not_allow',
				),
			)
		);
	}
}
