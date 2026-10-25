<?php
/**
 * Plugin bootstrap.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

use Forsamlingshus\Billetter\Admin\BroadcastPage;
use Forsamlingshus\Billetter\Admin\CheckinPage;
use Forsamlingshus\Billetter\Admin\DesignPage;
use Forsamlingshus\Billetter\Admin\Menu;
use Forsamlingshus\Billetter\Admin\Notices;
use Forsamlingshus\Billetter\Admin\OrdersPage;
use Forsamlingshus\Billetter\Admin\ParticipantsPage;
use Forsamlingshus\Billetter\Admin\SettingsPage;
use Forsamlingshus\Billetter\Elementor\Integration;
use Forsamlingshus\Billetter\Mail\TicketMailer;
use Forsamlingshus\Billetter\Payments\InvoiceGateway;
use Forsamlingshus\Billetter\Payments\PaymentMethods;
use Forsamlingshus\Billetter\Payments\StripeGateway;
use Forsamlingshus\Billetter\Webhook\StripeWebhook;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin together after WordPress has loaded.
 */
final class Plugin {

	public function boot(): void {
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		( new Database() )->install_if_needed();
		Capabilities::ensure();

		$orders = new OrderRepository();
		$tickets = new TicketRepository();
		$stock = new Stock();
		$settings = new Settings();
		$design = new Design();
		$methods = new PaymentMethods();
		$methods->register( new StripeGateway( $settings, $orders ) );
		$methods->register( new InvoiceGateway() );
		do_action( 'fhb_register_payment_methods', $methods );

		$mailer = new TicketMailer( $orders, $tickets, $design, $settings );
		$fulfillment = new Fulfillment( $orders, $tickets, $stock, $mailer, $settings );
		$sales = new SalesPage( $stock, $methods, $orders, $fulfillment );

		( new TicketShortcodes() )->hooks();
		( new Integration() )->hooks();
		( new Updates( $settings ) )->hooks();
		( new EventPostType( $stock, $sales ) )->hooks();
		$sales->hooks();
		( new Checkout( $orders, $stock, $methods, $fulfillment, $mailer ) )->hooks();
		( new StripeWebhook( $settings, $orders, $fulfillment ) )->hooks();
		( new Cron( $fulfillment ) )->hooks();
		( new Privacy( $orders ) )->hooks();
		( new Menu() )->hooks();
		( new OrdersPage( $orders, $fulfillment, $mailer, $tickets ) )->hooks();
		( new ParticipantsPage( $tickets ) )->hooks();
		( new DesignPage( $design ) )->hooks();
		( new SettingsPage( $settings ) )->hooks();
		( new BroadcastPage( $orders ) )->hooks();
		( new CheckinPage( new Checkin( $tickets, $orders ) ) )->hooks();
		( new Notices() )->hooks();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'forsamlingshus-billetter', false, dirname( plugin_basename( FHB_FILE ) ) . '/languages' );
	}
}
