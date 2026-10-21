<?php
/**
 * Public assets and single-event helpers.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Public
 */
class EB_Public {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'the_content', array( $this, 'append_tickets_on_single' ) );
		add_filter( 'the_content', array( $this, 'prepend_checkout_status' ) );
	}

	/**
	 * Enqueue frontend assets when shortcode/event present.
	 */
	public function enqueue() {
		$load = is_singular( 'eb_event' );

		if ( ! $load && is_singular() ) {
			$post = get_post();
			if ( $post && ( has_shortcode( $post->post_content, 'eb_tickets' ) || has_shortcode( $post->post_content, 'eb_checkout_status' ) ) ) {
				$load = true;
			}
		}

		// Always allow status display on return URLs.
		if ( ! empty( $_GET['eb_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$load = true;
		}

		if ( ! $load ) {
			return;
		}

		wp_enqueue_style(
			'eb-public',
			EB_PLUGIN_URL . 'assets/css/public.css',
			array(),
			EB_VERSION
		);

		wp_enqueue_script(
			'eb-checkout',
			EB_PLUGIN_URL . 'assets/js/checkout.js',
			array(),
			EB_VERSION,
			true
		);

		wp_localize_script(
			'eb-checkout',
			'ebCheckout',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'eb_checkout' ),
				'i18n'    => array(
					'processing' => __( 'Sender dig til betaling…', 'eventbilletter' ),
					'error'      => __( 'Noget gik galt. Prøv igen.', 'eventbilletter' ),
				),
			)
		);
	}

	/**
	 * Auto-append ticket form on single event if shortcode not present.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function append_tickets_on_single( $content ) {
		if ( ! is_singular( 'eb_event' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( has_shortcode( $content, 'eb_tickets' ) ) {
			return $content;
		}

		return $content . do_shortcode( '[eb_tickets]' );
	}

	/**
	 * Show checkout status on return pages even without shortcode.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function prepend_checkout_status( $content ) {
		if ( empty( $_GET['eb_checkout'] ) || ! in_the_loop() || ! is_main_query() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $content;
		}

		if ( has_shortcode( $content, 'eb_checkout_status' ) ) {
			return $content;
		}

		return do_shortcode( '[eb_checkout_status]' ) . $content;
	}
}
