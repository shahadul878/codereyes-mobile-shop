<?php
/**
 * IMEI normalisation and validation.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * An IMEI is 15 digits whose last digit is a Luhn check digit.
 */
final class Cmshop_Imei {

	public const LENGTH = 15;

	/**
	 * Strip separators and convert Bengali digits.
	 *
	 * Letters are kept so that validation rejects them instead of silently
	 * turning "49015420323751A" into a 14-digit number.
	 *
	 * @param string $raw Raw IMEI as typed or scanned.
	 * @return string
	 */
	public static function normalize( string $raw ): string {
		$ascii = Cmshop_Digits::to_ascii( $raw );

		return (string) preg_replace( '/[\s\-\/.]+/u', '', $ascii );
	}

	/**
	 * Whether a normalised value is a valid IMEI.
	 *
	 * @param string $imei Normalised IMEI.
	 * @return bool
	 */
	public static function is_valid( string $imei ): bool {
		if ( 1 !== preg_match( '/^\d{' . self::LENGTH . '}$/', $imei ) ) {
			return false;
		}

		if ( '' === trim( $imei, '0' ) ) {
			return false;
		}

		return self::passes_luhn( $imei );
	}

	/**
	 * Luhn checksum: double every second digit from the right.
	 *
	 * @param string $digits Digits only.
	 * @return bool
	 */
	private static function passes_luhn( string $digits ): bool {
		$sum    = 0;
		$length = strlen( $digits );

		for ( $i = 0; $i < $length; $i++ ) {
			$digit = (int) $digits[ $length - 1 - $i ];

			if ( 1 === $i % 2 ) {
				$digit *= 2;
				$digit  = $digit > 9 ? $digit - 9 : $digit;
			}

			$sum += $digit;
		}

		return 0 === $sum % 10;
	}
}
