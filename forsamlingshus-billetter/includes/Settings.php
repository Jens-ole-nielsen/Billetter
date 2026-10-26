<?php
/**
 * Stripe keys and other options.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * API keys live in a non-autoloaded option. Nothing secret is stored in the repo.
 */
final class Settings {

	public const OPTION = 'fhb_settings';

	/**
	 * @return array{publishable_key:string,secret_key:string,webhook_secret:string,github_token:string,mailpoet_newsletter_id:int}
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array(
			'publishable_key' => isset( $stored['publishable_key'] ) ? (string) $stored['publishable_key'] : '',
			'secret_key' => isset( $stored['secret_key'] ) ? (string) $stored['secret_key'] : '',
			'webhook_secret' => isset( $stored['webhook_secret'] ) ? (string) $stored['webhook_secret'] : '',
			'github_token' => isset( $stored['github_token'] ) ? (string) $stored['github_token'] : '',
			'mailpoet_newsletter_id' => isset( $stored['mailpoet_newsletter_id'] ) ? absint( $stored['mailpoet_newsletter_id'] ) : 0,
		);
	}

	public function publishable_key(): string {
		return $this->all()['publishable_key'];
	}

	public function secret_key(): string {
		return $this->all()['secret_key'];
	}

	public function webhook_secret(): string {
		return $this->all()['webhook_secret'];
	}

	public function github_token(): string {
		return $this->all()['github_token'];
	}

	public function mailpoet_newsletter_id(): int {
		return $this->all()['mailpoet_newsletter_id'];
	}

	public function stripe_ready(): bool {
		return str_starts_with( $this->secret_key(), 'sk_' );
	}

	/**
	 * @param array{publishable_key:string,secret_key:string,webhook_secret:string,github_token:string,mailpoet_newsletter_id:int} $values
	 */
	public function save( array $values ): void {
		update_option(
			self::OPTION,
			array(
				'publishable_key' => (string) $values['publishable_key'],
				'secret_key' => (string) $values['secret_key'],
				'webhook_secret' => (string) $values['webhook_secret'],
				'github_token' => (string) $values['github_token'],
				'mailpoet_newsletter_id' => absint( $values['mailpoet_newsletter_id'] ),
			),
			false
		);
	}
}
