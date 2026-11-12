<?php
/**
 * Customer phone number normalisation.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Phone numbers are stored as digits with an optional leading "+".
 */
final class Cmshop_Phone_Number {

	private const MIN_DIGITS = 6;
	private const MAX_DIGITS = 15;

	/**
	 * Normalise "+880 1712-345678" to "+8801712345678" and "০১৭১২" to "01712".
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	public static function normalize( string $raw ): string {
		$trimmed = trim( Cmshop_Digits::to_ascii( $raw ) );
		$prefix  = 0 === strpos( $trimmed, '+' ) ? '+' : '';

		return $prefix . Cmshop_Digits::only( $trimmed );
	}

	/**
	 * Whether a normalised number looks like a phone number.
	 *
	 * @param string $phone Normalised number.
	 * @return bool
	 */
	public static function is_valid( string $phone ): bool {
		$length = strlen( ltrim( $phone, '+' ) );

		return $length >= self::MIN_DIGITS && $length <= self::MAX_DIGITS;
	}

	/**
	 * Last four digits, used to confirm a repair lookup without exposing the number.
	 *
	 * @param string $phone Normalised number.
	 * @return string
	 */
	public static function last_four( string $phone ): string {
		return substr( Cmshop_Digits::only( $phone ), -4 );
	}
}
