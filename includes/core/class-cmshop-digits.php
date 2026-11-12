<?php
/**
 * Digit helpers shared by IMEI, phone and money parsing.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Converts Bengali digits to ASCII and strips everything else.
 */
final class Cmshop_Digits {

	private const BENGALI_DIGITS = array( '০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯' );
	private const ASCII_DIGITS   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

	/**
	 * Replace Bengali digits (০-৯) with ASCII digits, leaving other characters.
	 *
	 * @param string $value Raw input.
	 * @return string
	 */
	public static function to_ascii( string $value ): string {
		return str_replace( self::BENGALI_DIGITS, self::ASCII_DIGITS, $value );
	}

	/**
	 * Keep only ASCII digits, after converting Bengali digits.
	 *
	 * @param string $value Raw input.
	 * @return string
	 */
	public static function only( string $value ): string {
		return (string) preg_replace( '/\D+/', '', self::to_ascii( $value ) );
	}
}
