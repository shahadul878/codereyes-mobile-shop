<?php
/**
 * Print formats for sale invoices: thermal receipt roll or A4 page.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * The available invoice formats and thermal paper widths.
 */
final class Cmshop_Invoice_Format {

	public const THERMAL = 'thermal';
	public const A4      = 'a4';

	private const DEFAULT_WIDTH = '80';

	/**
	 * Formats, keyed by slug.
	 *
	 * @return array<string,string>
	 */
	public static function all(): array {
		return array(
			self::THERMAL => __( 'Thermal receipt', 'codereyes-mobile-shop' ),
			self::A4      => __( 'A4 invoice', 'codereyes-mobile-shop' ),
		);
	}

	/**
	 * Thermal paper roll widths in millimetres.
	 *
	 * @return array<string,string>
	 */
	public static function thermal_widths(): array {
		return array(
			'80' => __( '80 mm (standard)', 'codereyes-mobile-shop' ),
			'58' => __( '58 mm (small)', 'codereyes-mobile-shop' ),
		);
	}

	/**
	 * The format to show: the requested one if valid, else the shop default, else thermal.
	 *
	 * @param string $requested Format asked for (e.g. from the URL).
	 * @param string $fallback  Shop default.
	 * @return string
	 */
	public static function resolve( string $requested, string $fallback ): string {
		$formats = array_keys( self::all() );

		if ( in_array( $requested, $formats, true ) ) {
			return $requested;
		}

		return in_array( $fallback, $formats, true ) ? $fallback : self::THERMAL;
	}

	/**
	 * A valid thermal width, defaulting to 80 mm.
	 *
	 * @param string $width Width to check.
	 * @return string
	 */
	public static function thermal_width( string $width ): string {
		return array_key_exists( $width, self::thermal_widths() ) ? $width : self::DEFAULT_WIDTH;
	}
}
