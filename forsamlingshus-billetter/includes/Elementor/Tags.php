<?php
/**
 * Elementor dynamic tags. Loaded only when Elementor is present.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Elementor;

use Forsamlingshus\Billetter\TicketContext;
use Forsamlingshus\Billetter\TicketShortcodes;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {
	return;
}

/**
 * Builds one tag instance per ticket field.
 */
final class TicketTags {

	/**
	 * @return list<\Elementor\Core\DynamicTags\Tag>
	 */
	public static function make(): array {
		return array(
			new EventTag( array( 'id' => 'fhb_event', 'settings' => array() ) ),
			new DateTag( array( 'id' => 'fhb_date', 'settings' => array() ) ),
			new TimeTag( array( 'id' => 'fhb_time', 'settings' => array() ) ),
			new BuyerTag( array( 'id' => 'fhb_buyer', 'settings' => array() ) ),
			new PriceTag( array( 'id' => 'fhb_price', 'settings' => array() ) ),
			new RemarkTag( array( 'id' => 'fhb_remark', 'settings' => array() ) ),
			new QrTag( array( 'id' => 'fhb_qr', 'settings' => array() ) ),
		);
	}
}

abstract class TicketTag extends \Elementor\Core\DynamicTags\Tag {

	abstract protected function context_key(): string;

	public function get_group() {
		return 'fhb-ticket';
	}

	public function get_categories() {
		$category = class_exists( '\Elementor\Modules\DynamicTags\Module' )
			? \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY
			: 'text';

		return array( $category );
	}

	public function render(): void {
		if ( 'qr' === $this->context_key() ) {
			if ( '' !== TicketContext::get( 'token' ) ) {
				echo TicketShortcodes::QR_PLACEHOLDER; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- placeholder swapped for an image after render.
			}
			return;
		}

		echo esc_html( TicketContext::get( $this->context_key() ) );
	}
}

final class EventTag extends TicketTag {

	public function get_name() {
		return 'fhb_event';
	}

	public function get_title() {
		return __( 'Arrangement', 'forsamlingshus-billetter' );
	}

	protected function context_key(): string {
		return 'event';
	}
}

final class DateTag extends TicketTag {

	public function get_name() {
		return 'fhb_date';
	}

	public function get_title() {
		return __( 'Dato', 'forsamlingshus-billetter' );
	}

	protected function context_key(): string {
		return 'date';
	}
}

final class TimeTag extends TicketTag {

	public function get_name() {
		return 'fhb_time';
	}

	public function get_title() {
		return __( 'Tidspunkt', 'forsamlingshus-billetter' );
	}

	protected function context_key(): string {
		return 'time';
	}
}

final class BuyerTag extends TicketTag {

	public function get_name() {
		return 'fhb_buyer';
	}

	public function get_title() {
		return __( 'Køber', 'forsamlingshus-billetter' );
	}

	protected function context_key(): string {
		return 'buyer';
	}
}

final class PriceTag extends TicketTag {

	public function get_name() {
		return 'fhb_price';
	}

	public function get_title() {
		return __( 'Pris', 'forsamlingshus-billetter' );
	}

	protected function context_key(): string {
		return 'price';
	}
}

final class RemarkTag extends TicketTag {

	public function get_name() {
		return 'fhb_remark';
	}

	public function get_title() {
		return __( 'Bemærkning', 'forsamlingshus-billetter' );
	}

	protected function context_key(): string {
		return 'remark';
	}
}

final class QrTag extends TicketTag {

	public function get_name() {
		return 'fhb_qr';
	}

	public function get_title() {
		return __( 'QR-kode', 'forsamlingshus-billetter' );
	}

	protected function context_key(): string {
		return 'qr';
	}
}
