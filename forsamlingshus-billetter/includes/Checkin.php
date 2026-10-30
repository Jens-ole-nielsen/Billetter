<?php
/**
 * Door check-in.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * One scan marks one ticket. A second scan reports the rest of the order.
 */
final class Checkin {

	public function __construct(
		private TicketRepository $tickets,
		private OrderRepository $orders
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function scan( string $raw_token, int $user_id ): array {
		$token = Token::normalize( $raw_token );
		if ( ! Token::is_valid_format( $token ) ) {
			return array(
				'result' => 'unknown',
				'message' => __( 'Ugyldig kode.', 'forsamlingshus-billetter' ),
				'tickets' => array(),
				'unused' => array(),
			);
		}

		$ticket = $this->tickets->find_by_token( $token );
		if ( ! $ticket ) {
			return array(
				'result' => 'unknown',
				'message' => __( 'Ukendt billet.', 'forsamlingshus-billetter' ),
				'tickets' => array(),
				'unused' => array(),
			);
		}

		$order = $this->orders->find( (int) $ticket->order_id );
		if ( 'cancelled' === (string) $ticket->status ) {
			return $this->payload( 'invalid', __( 'Billetten er annulleret.', 'forsamlingshus-billetter' ), $ticket, $order );
		}

		if ( 'checked_in' === (string) $ticket->status ) {
			return $this->payload( 'already', __( 'Billetten er allerede tjekket ind.', 'forsamlingshus-billetter' ), $ticket, $order );
		}

		$marked = $this->tickets->mark_checked_in( (int) $ticket->id, $user_id );
		$fresh = $this->tickets->find_by_token( $token );
		$current = $fresh ? $fresh : $ticket;
		if ( ! $marked ) {
			return $this->payload( 'already', __( 'Billetten er allerede tjekket ind.', 'forsamlingshus-billetter' ), $current, $order );
		}

		return $this->payload( 'checked_in', __( 'Billetten er registreret som fremmødt.', 'forsamlingshus-billetter' ), $current, $order );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function payload( string $result, string $message, object $ticket, ?object $order ): array {
		$rows = $this->tickets->for_order( (int) $ticket->order_id );
		$tickets = array();
		$unused = array();
		foreach ( $rows as $row ) {
			$status = (string) $row->status;
			$tickets[] = array(
				'sequence' => (int) $row->sequence_no,
				'status' => $status,
				'checked_in_at' => Format::clock( isset( $row->checked_in_at ) ? (string) $row->checked_in_at : '' ),
			);
			if ( 'valid' === $status ) {
				$unused[] = (int) $row->sequence_no;
			}
		}

		$event = get_the_title( (int) $ticket->event_id );

		return array(
			'result' => $result,
			'message' => $message,
			'buyer' => $order ? (string) $order->buyer_name : '',
			'event' => is_string( $event ) ? $event : '',
			'sequence' => (int) $ticket->sequence_no,
			'quantity' => $order ? (int) $order->quantity : count( $rows ),
			'checked_in_at' => Format::clock( isset( $ticket->checked_in_at ) ? (string) $ticket->checked_in_at : '' ),
			'tickets' => $tickets,
			'unused' => $unused,
		);
	}
}
