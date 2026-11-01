<?php
/**
 * Personal data tools.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Exporter and eraser for the buyer fields stored on an order.
 */
final class Privacy {

	public function __construct( private OrderRepository $orders ) {
	}

	public function hooks(): void {
		add_action( 'admin_init', array( $this, 'policy' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	public function policy(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'Forsamlingshus Billetter',
			wp_kses_post(
				'<p>' . __( 'Når en gæst køber billetter, gemmer vi navn, e-mail, telefon og en eventuel bemærkning til ordren. Oplysningerne bruges til at sende billetterne og til at tjekke gæster ind. Kortbetaling sker hos Stripe. Fakturaordrer markeres manuelt som betalt af forsamlingshuset. Billetterne slettes ikke automatisk, når pluginnet deaktiveres.', 'forsamlingshus-billetter' ) . '</p>'
			)
		);
	}

	/**
	 * @param array<string, array<string, mixed>> $exporters
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['forsamlingshus-billetter'] = array(
			'exporter_friendly_name' => __( 'Forsamlingshus Billetter', 'forsamlingshus-billetter' ),
			'callback' => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * @param array<string, array<string, mixed>> $erasers
	 * @return array<string, array<string, mixed>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['forsamlingshus-billetter'] = array(
			'eraser_friendly_name' => __( 'Forsamlingshus Billetter', 'forsamlingshus-billetter' ),
			'callback' => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * @return array{data:list<array<string,mixed>>,done:bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		$data = array();
		foreach ( $this->orders->by_email( $email ) as $order ) {
			$data[] = array(
				'group_id' => 'fhb-orders',
				'group_label' => __( 'Billetordrer', 'forsamlingshus-billetter' ),
				'item_id' => 'fhb-order-' . (int) $order->id,
				'data' => array(
					array(
						'name' => __( 'Ordre', 'forsamlingshus-billetter' ),
						'value' => OrderNumber::format( (int) $order->id ),
					),
					array(
						'name' => __( 'Navn', 'forsamlingshus-billetter' ),
						'value' => (string) $order->buyer_name,
					),
					array(
						'name' => __( 'E-mail', 'forsamlingshus-billetter' ),
						'value' => (string) $order->buyer_email,
					),
					array(
						'name' => __( 'Telefon', 'forsamlingshus-billetter' ),
						'value' => (string) $order->buyer_phone,
					),
					array(
						'name' => __( 'Bemærkning', 'forsamlingshus-billetter' ),
						'value' => (string) $order->remark,
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * @return array{items_removed:bool,items_retained:bool,messages:list<string>,done:bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		$removed = false;
		foreach ( $this->orders->by_email( $email ) as $order ) {
			$this->orders->anonymize( (int) $order->id );
			$removed = true;
		}

		return array(
			'items_removed' => $removed,
			'items_retained' => $removed,
			'messages' => $removed ? array( __( 'Køberens navn, e-mail, telefon og bemærkning er fjernet fra ordren. Selve ordren beholdes.', 'forsamlingshus-billetter' ) ) : array(),
			'done' => true,
		);
	}
}
