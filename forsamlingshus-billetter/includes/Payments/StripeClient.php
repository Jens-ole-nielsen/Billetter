<?php
/**
 * Minimal Stripe HTTP client.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to the Stripe API with the secret key. Card data never touches this server.
 */
final class StripeClient {

	private const API = 'https://api.stripe.com/v1/';

	private const VERSION = '2024-06-20';

	public function __construct( private string $secret ) {
	}

	/**
	 * @param array<string, scalar> $body
	 * @return array<string, mixed>
	 */
	public function post( string $path, array $body, ?string $idempotency_key = null ): array {
		return $this->request( 'POST', $path, $body, $idempotency_key );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get( string $path ): array {
		return $this->request( 'GET', $path, null, null );
	}

	/**
	 * @param array<string, scalar>|null $body
	 * @return array<string, mixed>
	 */
	private function request( string $method, string $path, ?array $body, ?string $idempotency_key ): array {
		if ( '' === $this->secret ) {
			throw new StripeException( 'Stripe-nøglen mangler.' );
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $this->secret,
			'Stripe-Version' => self::VERSION,
		);
		if ( null !== $idempotency_key && '' !== $idempotency_key ) {
			$headers['Idempotency-Key'] = $idempotency_key;
		}

		$args = array(
			'method' => $method,
			'headers' => $headers,
			'timeout' => 20,
			'redirection' => 0,
		);
		if ( null !== $body ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( self::API . ltrim( $path, '/' ), $args );
		if ( is_wp_error( $response ) ) {
			throw new StripeException( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			throw new StripeException( 'Uventet svar fra Stripe.' );
		}
		if ( $code < 200 || $code >= 300 ) {
			$message = 'Stripe afviste kaldet.';
			$stripe_code = '';
			if ( isset( $data['error'] ) && is_array( $data['error'] ) ) {
				if ( isset( $data['error']['message'] ) && is_string( $data['error']['message'] ) ) {
					$message = $data['error']['message'];
				}
				if ( isset( $data['error']['code'] ) && is_string( $data['error']['code'] ) ) {
					$stripe_code = $data['error']['code'];
				}
			}
			throw new StripeException( self::redact( $message ), $code, $stripe_code );
		}

		return $data;
	}

	public static function redact( string $message ): string {
		$clean = preg_replace( '/sk_(live|test)_[A-Za-z0-9]+/', 'sk_…', $message );
		$message = is_string( $clean ) ? $clean : $message;
		$clean = preg_replace( '/pk_(live|test)_[A-Za-z0-9]+/', 'pk_…', $message );
		$message = is_string( $clean ) ? $clean : $message;
		$clean = preg_replace( '/whsec_[A-Za-z0-9]+/', 'whsec_…', $message );

		return mb_substr( is_string( $clean ) ? $clean : $message, 0, 240 );
	}
}
