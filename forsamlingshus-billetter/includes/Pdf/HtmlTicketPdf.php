<?php
/**
 * Turns a saved Elementor template into one PDF page per ticket.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Forsamlingshus\Billetter\TicketContext;
use Forsamlingshus\Billetter\TicketShortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * dompdf draws a simple document. Complex Elementor layout CSS may not match the editor.
 */
final class HtmlTicketPdf {

	/**
	 * @param list<array{token:string,sequence:int}> $tickets
	 * @param array<string, mixed>                   $context
	 */
	public function render( int $template_id, array $tickets, array $context ): ?string {
		if ( $template_id < 1 || array() === $tickets ) {
			return null;
		}
		if ( ! class_exists( Dompdf::class ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return null;
		}
		if ( ! isset( \Elementor\Plugin::$instance->frontend ) || ! is_object( \Elementor\Plugin::$instance->frontend ) ) {
			return null;
		}

		$pages = array();
		$total = count( $tickets );
		foreach ( $tickets as $ticket ) {
			$page = $this->page( $template_id, $ticket, $total, $context );
			if ( null === $page ) {
				TicketContext::clear();
				return null;
			}
			$pages[] = $page;
		}

		$document = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
			. '@page { margin: 12mm; }'
			. 'body { font-family: DejaVu Sans, sans-serif; color: #1c1c1c; }'
			. '.fhb-ticket-page { page-break-after: always; }'
			. '.fhb-ticket-page:last-child { page-break-after: auto; }'
			. 'img { max-width: 100%; height: auto; }'
			. '</style></head><body>' . implode( '', $pages ) . '</body></html>';

		try {
			$options = new Options();
			$options->set( 'defaultFont', 'DejaVu Sans' );
			$options->set( 'isRemoteEnabled', true );
			$options->set( 'isHtml5ParserEnabled', true );
			$dompdf = new Dompdf( $options );
			$dompdf->loadHtml( $document, 'UTF-8' );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			$binary = $dompdf->output();
		} catch ( \Throwable $error ) {
			unset( $error );
			return null;
		}

		if ( ! is_string( $binary ) || ! str_starts_with( $binary, '%PDF' ) ) {
			return null;
		}

		return $binary;
	}

	/**
	 * @param array{token:string,sequence:int} $ticket
	 * @param array<string, mixed>             $context
	 */
	private function page( int $template_id, array $ticket, int $total, array $context ): ?string {
		$sequence = (int) $ticket['sequence'];
		TicketContext::set(
			array(
				'event' => (string) ( $context['event'] ?? '' ),
				'date' => (string) ( $context['date'] ?? '' ),
				'time' => (string) ( $context['time'] ?? '' ),
				'buyer' => (string) ( $context['buyer'] ?? '' ),
				'price' => (string) ( $context['price'] ?? '' ),
				'remark' => (string) ( $context['remark'] ?? '' ),
				'order_number' => (string) ( $context['order_number'] ?? '' ),
				'quantity' => (string) (int) ( $context['quantity'] ?? $total ),
				'amount' => (string) ( $context['amount'] ?? '' ),
				'token' => (string) $ticket['token'],
				'sequence_label' => sprintf(
					/* translators: 1: ticket number, 2: tickets in the order */
					__( 'Billet %1$d af %2$d', 'forsamlingshus-billetter' ),
					$sequence,
					$total
				),
			)
		);

		$level = ob_get_level();
		try {
			ob_start();
			$html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template_id, true );
		} catch ( \Throwable $error ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			unset( $error );
			TicketContext::clear();
			return null;
		}
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}

		if ( ! is_string( $html ) ) {
			TicketContext::clear();
			return null;
		}
		$html = do_shortcode( $html );
		TicketContext::clear();
		$html = TicketShortcodes::replace_qr( $html, (string) $ticket['token'] );
		$html = $this->strip_layout_css( $html );
		$plain = trim( wp_strip_all_tags( $html ) );
		if ( '' === $plain && ! str_contains( $html, '<img' ) ) {
			return null;
		}

		return '<div class="fhb-ticket-page">' . $html . '</div>';
	}

	/**
	 * Elementor layout CSS uses variables dompdf cannot parse. The ticket text and images stay.
	 */
	private function strip_layout_css( string $html ): string {
		$without_links = preg_replace( '/<link\b[^>]*>/i', '', $html );
		$html = is_string( $without_links ) ? $without_links : $html;
		$without_style = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $html );
		$html = is_string( $without_style ) ? $without_style : $html;
		$without_vars = preg_replace( '/var\(\s*--[^)]+\)/', 'inherit', $html );
		$html = is_string( $without_vars ) ? $without_vars : $html;
		$without_props = preg_replace( '/--[A-Za-z0-9_-]+\s*:[^;}]*/', '', $html );

		return is_string( $without_props ) ? $without_props : $html;
	}
}
