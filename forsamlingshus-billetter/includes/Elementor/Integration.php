<?php
/**
 * Elementor dynamic tags for a ticket template.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Registers tags only after Elementor asks for them, so a site without Elementor never loads them.
 */
final class Integration {

	public function hooks(): void {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_tags' ) );
	}

	public function register_tags( mixed $manager ): void {
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'register_group' ) || ! method_exists( $manager, 'register' ) ) {
			return;
		}
		if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {
			return;
		}

		require_once __DIR__ . '/Tags.php';

		$manager->register_group(
			'fhb-ticket',
			array(
				'title' => __( 'Billet', 'forsamlingshus-billetter' ),
			)
		);
		foreach ( TicketTags::make() as $tag ) {
			$manager->register( $tag );
		}
	}
}
