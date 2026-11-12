<?php
/**
 * Money parsing, arithmetic and formatting.
 *
 * Amounts are stored as DECIMAL(12,2) strings ("12500.50") and all arithmetic
 * is done in integer cents so totals never drift.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Helpers for amounts.
 */
final class Cmshop_Money {

	/**
	 * Largest amount a DECIMAL(12,2) column holds, in whole units.
	 */
	public const MAX_UNITS = 9999999999;

	/**
	 * Currency written before or after the amount: "৳ 500", "Tk500", "£20", "500 BDT".
	 */
	private const CURRENCY_AFFIX = '/^(?:৳|\$|£|€|₹|tk\.?|bdt)\s*|\s*(?:৳|£|€|₹|tk\.?|bdt)$/iu';

	/**
	 * Parse user input such as "12,500", "৳ ১৫,০০০" or "99.5".
	 *
	 * @param string $raw Raw input.
	 * @return string|null Normalised "1234.50", or null when invalid or negative.
	 */
	public static function parse( string $raw ): ?string {
		$clean = trim( Cmshop_Digits::to_ascii( $raw ) );
		$clean = (string) preg_replace( self::CURRENCY_AFFIX, '', $clean );

		// Commas are thousands separators only: "15,000", lakh-style "1,50,000".
		if ( 1 !== preg_match( '/^(\d+|\d{1,3}(?:,\d{2,3})*,\d{3})(?:\.(\d{1,2}))?$/', $clean, $parts ) ) {
			return null;
		}

		$units = ltrim( str_replace( ',', '', $parts[1] ), '0' );
		$units = '' === $units ? '0' : $units;

		if ( strlen( $units ) > strlen( (string) self::MAX_UNITS ) || (int) $units > self::MAX_UNITS ) {
			return null;
		}

		return $units . '.' . str_pad( $parts[2] ?? '', 2, '0' );
	}

	/**
	 * Parse optional input: empty means zero.
	 *
	 * @param string $raw Raw input.
	 * @return string|null
	 */
	public static function parse_or_zero( string $raw ): ?string {
		return '' === trim( $raw ) ? '0.00' : self::parse( $raw );
	}

	/**
	 * Convert a stored amount to integer cents.
	 *
	 * @param string $amount Amount such as "12500.50" or "-300.25".
	 * @return int
	 */
	public static function to_cents( string $amount ): int {
		$negative = 0 === strpos( $amount, '-' );
		$parts    = explode( '.', ltrim( $amount, '-' ), 2 );
		$fraction = substr( str_pad( $parts[1] ?? '', 2, '0' ), 0, 2 );
		$cents    = ( (int) $parts[0] ) * 100 + (int) $fraction;

		return $negative ? -$cents : $cents;
	}

	/**
	 * Convert integer cents back to a "1234.50" string.
	 *
	 * @param int $cents Cents.
	 * @return string
	 */
	public static function from_cents( int $cents ): string {
		$sign     = $cents < 0 ? '-' : '';
		$absolute = abs( $cents );

		return sprintf( '%s%d.%02d', $sign, intdiv( $absolute, 100 ), $absolute % 100 );
	}

	/**
	 * Format for display: "৳12,500.50", "-৳500.00", or a dash when empty.
	 *
	 * @param string|null $amount Amount.
	 * @param string      $symbol Currency symbol.
	 * @return string
	 */
	public static function format( ?string $amount, string $symbol ): string {
		if ( null === $amount || '' === $amount ) {
			return '—';
		}

		$cents    = self::to_cents( $amount );
		$sign     = $cents < 0 ? '-' : '';
		$absolute = abs( $cents );

		return sprintf( '%s%s%s.%02d', $sign, $symbol, number_format( intdiv( $absolute, 100 ) ), $absolute % 100 );
	}
}
