<?php
/**
 * Shared rows for the order PDF and the CSV download.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Decides the payable total and writes the same columns to CSV.
 */
final class OrderExport {

	/**
	 * A stored zero is kept when a coupon made the order free.
	 * Without a coupon, a zero against a priced event is a missing total.
	 *
	 * @return array{amount_ore:int,discount_ore:int,coupon:string,repaired:bool}
	 */
	public static function totals( int $amount_ore, int $discount_ore, string $coupon, int $unit_ore, int $quantity ): array {
		$coupon = trim( $coupon );
		$discount_ore = max( 0, $discount_ore );
		$quantity = max( 0, $quantity );
		$gross = max( 0, $unit_ore ) * $quantity;
		$coupon_free = '' !== $coupon || $discount_ore > 0;

		if ( $amount_ore > 0 ) {
			return array(
				'amount_ore' => $amount_ore,
				'discount_ore' => $discount_ore,
				'coupon' => $coupon,
				'repaired' => false,
			);
		}

		if ( $coupon_free ) {
			return array(
				'amount_ore' => 0,
				'discount_ore' => $discount_ore > 0 ? $discount_ore : $gross,
				'coupon' => $coupon,
				'repaired' => false,
			);
		}

		if ( $gross > 0 ) {
			return array(
				'amount_ore' => $gross,
				'discount_ore' => 0,
				'coupon' => '',
				'repaired' => true,
			);
		}

		return array(
			'amount_ore' => 0,
			'discount_ore' => 0,
			'coupon' => '',
			'repaired' => false,
		);
	}

	public static function payment_label( string $method ): string {
		return match ( $method ) {
			'stripe' => 'Betalingskort',
			'invoice' => 'Faktura',
			'free' => 'Gratis',
			default => $method,
		};
	}

	public static function discount_label( string $coupon, int $discount_ore ): string {
		$coupon = trim( $coupon );
		$parts = array();
		if ( '' !== $coupon ) {
			$parts[] = $coupon;
		}
		if ( $discount_ore > 0 ) {
			$parts[] = 'Rabat ' . Money::format_ore( $discount_ore );
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Danish decimal without the "kr." suffix, so Excel can sum the column.
	 */
	public static function amount_csv( int $ore ): string {
		$negative = $ore < 0;
		$ore = abs( $ore );
		$kroner = intdiv( $ore, 100 );
		$cents = $ore % 100;
		$formatted = number_format( $kroner, 0, ',', '.' ) . ',' . str_pad( (string) $cents, 2, '0', STR_PAD_LEFT );

		return $negative ? '-' . $formatted : $formatted;
	}

	public static function csv_remark( string $remark, string $discount ): string {
		$remark = trim( $remark );
		$discount = trim( $discount );
		if ( '' === $discount ) {
			return $remark;
		}
		if ( '' === $remark ) {
			return $discount;
		}

		return $remark . ' · ' . $discount;
	}

	/**
	 * @param list<array{number:string,event:string,buyer:string,email:string,phone:string,quantity:int,amount_ore:int,discount:string,payment:string,remark:string}> $rows
	 */
	public static function csv( array $rows ): string {
		$lines = array(
			self::csv_line(
				array(
					'Ordrenummer',
					'Arrangement',
					'Køber',
					'E-mail',
					'Telefon',
					'Antal',
					'Beløb',
					'Betaling',
					'Bemærkning',
				)
			),
		);
		foreach ( $rows as $row ) {
			$lines[] = self::csv_line(
				array(
					(string) $row['number'],
					(string) $row['event'],
					(string) $row['buyer'],
					(string) $row['email'],
					(string) $row['phone'],
					(string) (int) $row['quantity'],
					self::amount_csv( (int) $row['amount_ore'] ),
					(string) $row['payment'],
					self::csv_remark( (string) $row['remark'], (string) $row['discount'] ),
				)
			);
		}

		return "\xEF\xBB\xBF" . implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * @param list<string> $fields
	 */
	private static function csv_line( array $fields ): string {
		$cells = array();
		foreach ( $fields as $field ) {
			$cells[] = self::csv_cell( $field );
		}

		return implode( ';', $cells );
	}

	private static function csv_cell( string $value ): string {
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		if ( str_contains( $value, ';' ) || str_contains( $value, '"' ) || str_contains( $value, "\n" ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}
}
