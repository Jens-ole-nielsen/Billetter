<?php
/**
 * Minimal Stripe REST client via WordPress HTTP API.
 *
 * @package Eventbilletter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EB_Stripe
 */
class EB_Stripe {

	const API_BASE = 'https://api.stripe.com/v1';

	/**
	 * Get secret key for current mode.
	 *
	 * @return string
	 */
	public static function get_secret_key() {
		$mode = eventbilletter()->get_setting( 'stripe_mode', 'test' );
		if ( 'live' === $mode ) {
			return (string) eventbilletter()->get_setting( 'stripe_live_secret', '' );
		}
		return (string) eventbilletter()->get_setting( 'stripe_test_secret', '' );
	}

	/**
	 * Get publishable key for current mode.
	 *
	 * @return string
	 */
	public static function get_publishable_key() {
		$mode = eventbilletter()->get_setting( 'stripe_mode', 'test' );
		if ( 'live' === $mode ) {
			return (string) eventbilletter()->get_setting( 'stripe_live_publishable', '' );
		}
		return (string) eventbilletter()->get_setting( 'stripe_test_publishable', '' );
	}

	/**
	 * Whether Stripe is configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return (bool) self::get_secret_key();
	}

	/**
	 * Create a Checkout Session.
	 *
	 * @param array $params Session params (already prepared).
	 * @return array|WP_Error Decoded Stripe response.
	 */
	public static function create_checkout_session( array $params ) {
		return self::request( 'POST', '/checkout/sessions', $params );
	}

	/**
	 * Retrieve a Checkout Session.
	 *
	 * @param string $session_id Session ID.
	 * @return array|WP_Error
	 */
	public static function retrieve_checkout_session( $session_id ) {
		return self::request( 'GET', '/checkout/sessions/' . rawurlencode( $session_id ) );
	}

	/**
	 * Convert major currency amount to Stripe minor units.
	 *
	 * @param float  $amount   Amount in major units (e.g. 150.00 DKK).
	 * @param string $currency Currency code.
	 * @return int
	 */
	public static function to_minor_units( $amount, $currency ) {
		$zero_decimal = array( 'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' );
		$currency     = strtolower( $currency );
		if ( in_array( $currency, $zero_decimal, true ) ) {
			return (int) round( $amount );
		}
		return (int) round( $amount * 100 );
	}

	/**
	 * Verify Stripe webhook signature.
	 *
	 * @param string $payload   Raw body.
	 * @param string $sig_header Stripe-Signature header.
	 * @param string $secret    Webhook signing secret.
	 * @return true|WP_Error
	 */
	public static function verify_webhook( $payload, $sig_header, $secret ) {
		if ( ! $secret ) {
			return new WP_Error( 'eb_no_webhook_secret', __( 'Webhook-hemmelighed mangler.', 'eventbilletter' ) );
		}

		$parts = array();
		foreach ( explode( ',', $sig_header ) as $piece ) {
			$kv = explode( '=', trim( $piece ), 2 );
			if ( 2 === count( $kv ) ) {
				$parts[ $kv[0] ][] = $kv[1];
			}
		}

		if ( empty( $parts['t'][0] ) || empty( $parts['v1'] ) ) {
			return new WP_Error( 'eb_bad_sig', __( 'Ugyldig Stripe-signatur.', 'eventbilletter' ) );
		}

		$timestamp = (int) $parts['t'][0];
		if ( abs( time() - $timestamp ) > 300 ) {
			return new WP_Error( 'eb_sig_expired', __( 'Stripe-signaturen er udløbet.', 'eventbilletter' ) );
		}

		$signed_payload = $timestamp . '.' . $payload;
		$expected       = hash_hmac( 'sha256', $signed_payload, $secret );

		$matched = false;
		foreach ( $parts['v1'] as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				$matched = true;
				break;
			}
		}

		if ( ! $matched ) {
			return new WP_Error( 'eb_sig_mismatch', __( 'Stripe-signatur matcher ikke.', 'eventbilletter' ) );
		}

		return true;
	}

	/**
	 * Perform a Stripe API request.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   API path.
	 * @param array  $params Body/query params.
	 * @return array|WP_Error
	 */
	public static function request( $method, $path, array $params = array() ) {
		$secret = self::get_secret_key();
		if ( ! $secret ) {
			return new WP_Error( 'eb_no_stripe_key', __( 'Stripe-nøgle er ikke konfigureret.', 'eventbilletter' ) );
		}

		$url = self::API_BASE . $path;

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $secret,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
		);

		if ( 'GET' === $method && ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		} elseif ( ! empty( $params ) ) {
			$args['body'] = self::build_query( $params );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Ukendt Stripe-fejl.', 'eventbilletter' );
			return new WP_Error( 'eb_stripe_error', $message, array( 'status' => $code, 'body' => $body ) );
		}

		return is_array( $body ) ? $body : array();
	}

	/**
	 * Build nested query string for Stripe (PHP http_build_query compatible).
	 *
	 * @param array $params Params.
	 * @return string
	 */
	private static function build_query( array $params ) {
		return http_build_query( $params, '', '&' );
	}
}
