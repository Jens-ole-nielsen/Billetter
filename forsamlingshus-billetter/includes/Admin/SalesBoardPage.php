<?php
/**
 * Phone page for following ticket sales.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Admin;

use Forsamlingshus\Billetter\Capabilities;
use Forsamlingshus\Billetter\Format;
use Forsamlingshus\Billetter\Money;
use Forsamlingshus\Billetter\OrderNumber;
use Forsamlingshus\Billetter\OrderRepository;
use Forsamlingshus\Billetter\OrderStatus;
use Forsamlingshus\Billetter\SalesBoard;
use Forsamlingshus\Billetter\Stock;
use Forsamlingshus\Billetter\View;

defined( 'ABSPATH' ) || exit;

/**
 * Same WordPress login as wp-admin. No separate password.
 * Rendered before the admin menu so a phone can read it.
 */
final class SalesBoardPage {

	public function __construct(
		private OrderRepository $orders,
		private Stock $stock
	) {
	}

	public function hooks(): void {
		if ( $this->path_is_public() ) {
			$this->block_cache();
		}
		add_action( 'init', array( $this, 'register_route' ), 0 );
		add_action( 'init', array( $this, 'flush_route_once' ), 999 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_public' ), 0 );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_render' ) );
	}

	public function register_route(): void {
		SalesBoard::register_rewrite();
	}

	/**
	 * Plugin updates do not run the activation hook, so the /salg rule is stored once.
	 */
	public function flush_route_once(): void {
		if ( get_option( SalesBoard::ROUTE_OPTION ) === SalesBoard::PUBLIC_SLUG ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( SalesBoard::ROUTE_OPTION, SalesBoard::PUBLIC_SLUG, false );
	}

	/**
	 * @param list<string> $vars
	 * @return list<string>
	 */
	public function query_vars( array $vars ): array {
		$vars[] = SalesBoard::QUERY_VAR;
		return $vars;
	}

	public function menu(): void {
		add_submenu_page(
			'fhb-tickets',
			__( 'Salg', 'forsamlingshus-billetter' ),
			__( 'Salg', 'forsamlingshus-billetter' ),
			Capabilities::ORDERS,
			SalesBoard::PAGE,
			'__return_null'
		);
	}

	/**
	 * WordPress has already sent a logged-out visitor to wp-login.php.
	 * This still checks, and it refuses anyone who cannot open Ordrer.
	 */
	public function maybe_render(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( SalesBoard::PAGE !== $page ) {
			return;
		}
		$this->authorize();
		nocache_headers();
		$this->send( admin_url( 'admin.php?page=' . SalesBoard::PAGE ) );
		exit;
	}

	/**
	 * /salg uses the same login and the same board as the wp-admin address.
	 */
	public function maybe_render_public(): void {
		if ( ! $this->path_is_public() ) {
			return;
		}
		$this->block_cache();
		$this->authorize();
		status_header( 200 );
		$this->send( home_url( '/' . SalesBoard::PUBLIC_SLUG . '/' ) );
		exit;
	}

	private function authorize(): void {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( ! Capabilities::can( Capabilities::ORDERS ) ) {
			wp_die( esc_html__( 'Du har ikke adgang til billetsalget.', 'forsamlingshus-billetter' ), '', array( 'response' => 403 ) );
		}
	}

	private function block_cache(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}

	private function path_is_public(): bool {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$home = '/';
		if ( function_exists( 'home_url' ) ) {
			$parsed = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
			if ( is_string( $parsed ) && '' !== $parsed ) {
				$home = $parsed;
			}
		}

		return SalesBoard::is_public_path( $uri, $home );
	}

	private function send( string $refresh ): void {
		$today = wp_date( 'Y-m-d' );
		$events = $this->events( $today );
		$totals = SalesBoard::totals( $events );
		$css = '';
		$css_file = FHB_PATH . 'assets/css/board.css';
		if ( is_file( $css_file ) ) {
			$css = str_replace( '</', '<\/', (string) file_get_contents( $css_file ) );
		}
		echo '<!DOCTYPE html><html lang="da"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<meta name="robots" content="noindex, nofollow">';
		echo '<meta http-equiv="refresh" content="60">';
		echo '<title>' . esc_html__( 'Billetsalg', 'forsamlingshus-billetter' ) . '</title>';
		echo '<style>' . $css . '</style></head><body class="fhb-board">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its own values.
		echo View::render(
			'board',
			array(
				'refreshed' => wp_date( 'H:i' ),
				'refresh_url' => $refresh,
				'sold' => (string) $totals['sold'],
				'remaining' => (string) $totals['remaining'],
				'amount' => Money::format_ore( $totals['amount_ore'] ),
				'events' => $this->event_rows( $events ),
				'orders' => $this->order_rows(),
			)
		);
		echo '</body></html>';
	}

	/**
	 * @return list<array{title:string,sold:int,remaining:int,amount_ore:int,date:string,time:string,reserved:int}>
	 */
	private function events( string $today ): array {
		$posts = get_posts(
			array(
				'post_type' => 'fhb_event',
				'post_status' => 'publish',
				'posts_per_page' => 50,
				'orderby' => 'meta_value',
				'meta_key' => '_fhb_date',
				'order' => 'ASC',
			)
		);
		if ( ! is_array( $posts ) ) {
			return array();
		}

		$rows = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$date = (string) get_post_meta( $post->ID, '_fhb_date', true );
			if ( ! SalesBoard::visible( $date, $today ) ) {
				continue;
			}
			$time = (string) get_post_meta( $post->ID, '_fhb_time', true );
			if ( 1 === preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ) {
				$time = substr( $time, 0, 5 );
			}
			$stock = $this->stock->get( (int) $post->ID );
			$reserved = $stock ? max( 0, (int) $stock->reserved ) : 0;
			$rows[] = array(
				'title' => get_the_title( $post ),
				'date' => $date,
				'time' => $time,
				'sold' => $this->stock->sold( (int) $post->ID ),
				'remaining' => $this->stock->remaining( (int) $post->ID ),
				'reserved' => $reserved,
				'amount_ore' => $this->orders->paid_net_ore( (int) $post->ID ),
			);
		}

		return $rows;
	}

	/**
	 * @param list<array{title:string,sold:int,remaining:int,amount_ore:int,date:string,time:string,reserved:int}> $events
	 * @return list<array{title:string,when:string,sold:string,remaining:string,amount:string,reserved:string}>
	 */
	private function event_rows( array $events ): array {
		$rows = array();
		foreach ( $events as $event ) {
			$when = Format::date( $event['date'] );
			$time = Format::time( $event['time'] );
			if ( '' !== $time ) {
				$when = '' === $when ? $time : $when . ' · ' . sprintf( /* translators: %s: time */ __( 'Kl. %s', 'forsamlingshus-billetter' ), $time );
			}
			$rows[] = array(
				'title' => $event['title'],
				'when' => $when,
				'sold' => (string) $event['sold'],
				'remaining' => (string) $event['remaining'],
				'amount' => Money::format_ore( $event['amount_ore'] ),
				'reserved' => $event['reserved'] > 0 ? (string) $event['reserved'] : '',
			);
		}

		return $rows;
	}

	/**
	 * @return list<array{number:string,buyer:string,event:string,quantity:string,amount:string,status:string,when:string}>
	 */
	private function order_rows(): array {
		$rows = array();
		foreach ( $this->orders->latest_for_board( SalesBoard::RECENT ) as $order ) {
			$qty = max( 0, (int) $order->quantity );
			$rows[] = array(
				'number' => OrderNumber::format( (int) $order->id ),
				'buyer' => (string) $order->buyer_name,
				'event' => get_the_title( (int) $order->event_id ),
				'quantity' => sprintf(
					/* translators: %d: ticket count */
					_n( '%d billet', '%d billetter', $qty, 'forsamlingshus-billetter' ),
					$qty
				),
				'amount' => Money::format_ore( (int) $order->amount_ore ),
				'status' => OrderStatus::label( (string) $order->status ),
				'when' => Format::when( (string) $order->created_at ),
			);
		}

		return $rows;
	}
}
