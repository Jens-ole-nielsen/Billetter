<?php
/**
 * Public sales page.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

use Forsamlingshus\Billetter\Payments\PaymentMethods;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a real WordPress page in step with the event and renders the checkout.
 */
final class SalesPage {

	public function __construct(
		private Stock $stock,
		private PaymentMethods $methods,
		private OrderRepository $orders,
		private Fulfillment $fulfillment
	) {
	}

	public function hooks(): void {
		add_shortcode( 'fhb_event', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'print_late_style' ) );
		add_action( 'template_redirect', array( $this, 'release_cancelled_checkout' ) );
	}

	public function sync( int $event_id ): int {
		$event = Events::get( $event_id );
		if ( ! $event || 'auto-draft' === $event['status'] ) {
			return 0;
		}

		$page_id = (int) $event['sales_page_id'];
		$page = $page_id > 0 ? get_post( $page_id ) : null;
		if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type ) {
			$page = null;
			$page_id = 0;
		}

		$shortcode = '[fhb_event id="' . $event_id . '"]';
		$content = $shortcode;
		if ( $page && str_contains( (string) $page->post_content, '[fhb_event' ) ) {
			$content = (string) $page->post_content;
		}
		$title = '' !== (string) $event['title'] ? (string) $event['title'] : __( 'Arrangement', 'forsamlingshus-billetter' );
		$status = 'publish' === $event['status'] ? 'publish' : 'draft';
		$postarr = array(
			'post_type' => 'page',
			'post_status' => $status,
			'post_title' => $title,
			'post_content' => $content,
			'comment_status' => 'closed',
			'ping_status' => 'closed',
		);

		if ( $page ) {
			$postarr['ID'] = (int) $page->ID;
			$updated = wp_update_post( $postarr, true );
			if ( is_wp_error( $updated ) ) {
				return (int) $page->ID;
			}
			$page_id = (int) $page->ID;
		} else {
			$postarr['post_name'] = sanitize_title( $title );
			$created = wp_insert_post( $postarr, true );
			if ( is_wp_error( $created ) ) {
				return 0;
			}
			$page_id = (int) $created;
		}

		update_post_meta( $event_id, '_fhb_sales_page_id', $page_id );
		update_post_meta( $page_id, '_fhb_event_id', $event_id );

		return $page_id;
	}

	public function enqueue(): void {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! str_contains( (string) $post->post_content, '[fhb_event' ) ) {
			return;
		}
		wp_enqueue_style( 'fhb-front', FHB_URL . 'assets/css/front.css', array(), FHB_VERSION );
		$this->enqueue_checkout_script();
	}

	public function print_late_style(): void {
		if ( wp_style_is( 'fhb-front', 'enqueued' ) && ! wp_style_is( 'fhb-front', 'done' ) ) {
			wp_print_styles( 'fhb-front' );
		}
		if ( wp_script_is( 'fhb-checkout', 'enqueued' ) && ! wp_script_is( 'fhb-checkout', 'done' ) ) {
			wp_print_scripts( 'fhb-checkout' );
		}
	}

	private function enqueue_checkout_script(): void {
		wp_enqueue_script( 'fhb-checkout', FHB_URL . 'assets/js/checkout.js', array(), FHB_VERSION, true );
	}

	/**
	 * @param array<string, string> $atts
	 */
	public function shortcode( array $atts ): string {
		$atts = shortcode_atts( array( 'id' => '0' ), $atts, 'fhb_event' );
		$event = Events::get( (int) $atts['id'] );
		if ( ! $event || 'publish' !== $event['status'] ) {
			return '<p class="fhb-error">' . esc_html__( 'Dette arrangement er ikke til salg.', 'forsamlingshus-billetter' ) . '</p>';
		}

		wp_enqueue_style( 'fhb-front', FHB_URL . 'assets/css/front.css', array(), FHB_VERSION );
		$this->enqueue_checkout_script();

		$result = isset( $_GET['fhb_result'] ) ? sanitize_key( wp_unslash( $_GET['fhb_result'] ) ) : '';
		$error = isset( $_GET['fhb_error'] ) ? sanitize_key( wp_unslash( $_GET['fhb_error'] ) ) : '';
		$token = isset( $_GET['order'] ) ? Token::normalize( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : '';
		$order = Token::is_valid_format( $token ) ? $this->orders->find_by_public_token( $token ) : null;
		if ( $order && (int) $order->event_id !== (int) $event['id'] ) {
			$order = null;
		}

		$remaining = $this->stock->remaining( (int) $event['id'] );
		$methods = array();
		foreach ( $this->methods->for_checkout( $event ) as $method ) {
			$methods[] = array(
				'id' => $method->id(),
				'label' => $method->label(),
				'description' => $method->description(),
			);
		}

		return View::render(
			'sales',
			array(
				'event' => $event,
				'remaining' => $remaining,
				'methods' => $methods,
				'result' => $result,
				'error' => $error,
				'order' => $order,
				'max_qty' => min( Checkout::MAX_PER_ORDER, max( 0, $remaining ) ),
			)
		);
	}

	public function release_cancelled_checkout(): void {
		if ( ! isset( $_GET['fhb_result'], $_GET['order'] ) ) {
			return;
		}
		if ( 'cancel' !== sanitize_key( wp_unslash( $_GET['fhb_result'] ) ) ) {
			return;
		}
		$token = Token::normalize( sanitize_text_field( wp_unslash( $_GET['order'] ) ) );
		if ( ! Token::is_valid_format( $token ) ) {
			return;
		}
		$order = $this->orders->find_by_public_token( $token );
		if ( ! $order || OrderStatus::PENDING !== (string) $order->status ) {
			return;
		}
		$this->fulfillment->cancel_unpaid( (int) $order->id, true );
	}
}
