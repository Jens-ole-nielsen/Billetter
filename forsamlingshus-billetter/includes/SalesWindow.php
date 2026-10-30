<?php
/**
 * When ticket sales open for an event.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * An empty start means sales are open immediately. A stored start is site-local.
 */
final class SalesWindow {

	/**
	 * Empty input stays empty. A real date and time becomes Y-m-d H:i. Anything else is rejected.
	 */
	public static function normalize( string $raw ): ?string {
		$raw = trim( str_replace( 'T', ' ', $raw ) );
		if ( '' === $raw ) {
			return '';
		}
		if ( 1 !== preg_match( '/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})(?::\d{2})?$/', $raw, $match ) ) {
			return null;
		}
		$stamp = $match[1] . ' ' . $match[2];
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $stamp );
		$errors = \DateTimeImmutable::getLastErrors();
		if ( ! $parsed || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
			return null;
		}
		if ( $parsed->format( 'Y-m-d H:i' ) !== $stamp ) {
			return null;
		}

		return $stamp;
	}

	/**
	 * Value for input type="datetime-local".
	 */
	public static function input_value( string $stored ): string {
		$normalized = self::normalize( $stored );
		if ( null === $normalized || '' === $normalized ) {
			return '';
		}

		return str_replace( ' ', 'T', $normalized );
	}

	public static function start_timestamp( string $stored, \DateTimeZone $zone ): ?int {
		$normalized = self::normalize( $stored );
		if ( null === $normalized || '' === $normalized ) {
			return null;
		}
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $normalized, $zone );
		if ( ! $parsed ) {
			return null;
		}

		return $parsed->getTimestamp();
	}

	/**
	 * Empty or unreadable starts stay open, matching events saved before the field existed.
	 */
	public static function is_open( string $stored, int $now, \DateTimeZone $zone ): bool {
		$start = self::start_timestamp( $stored, $zone );
		if ( null === $start ) {
			return true;
		}

		return $now >= $start;
	}

	public static function countdown_text( int $now, int $start ): string {
		$left = $start - $now;
		if ( $left <= 0 ) {
			return 'Billetsalget er åbent.';
		}
		$days = intdiv( $left, 86400 );
		$left %= 86400;
		$hours = intdiv( $left, 3600 );
		$left %= 3600;
		$minutes = intdiv( $left, 60 );
		$seconds = $left % 60;
		$bits = array();
		if ( $days > 0 ) {
			$bits[] = $days . ( 1 === $days ? ' dag' : ' dage' );
		}
		if ( $hours > 0 ) {
			$bits[] = $hours . ( 1 === $hours ? ' time' : ' timer' );
		}
		if ( $minutes > 0 || ( 0 === $days && 0 === $hours ) ) {
			$bits[] = $minutes . ( 1 === $minutes ? ' minut' : ' minutter' );
		}
		$bits[] = $seconds . ( 1 === $seconds ? ' sekund' : ' sekunder' );
		$line = implode( ', ', $bits );
		$last = strrpos( $line, ', ' );
		if ( false !== $last ) {
			$line = substr( $line, 0, $last ) . ' og ' . substr( $line, $last + 2 );
		}

		return 'Salget starter om ' . $line . '.';
	}
}
