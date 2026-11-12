<?php
/**
 * Code 128 barcodes as inline SVG.
 *
 * @package CodereyesMobileShop
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are for staff and are escaped where they are displayed.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Encodes IMEIs, SKUs and item codes so any USB/Bluetooth barcode scanner
 * can read them back into the POS search box. Digits are packed two per
 * symbol (code set C), so a 15-digit IMEI stays short enough for a 38 mm label.
 */
final class Cmshop_Code128 {

	public const MAX_LENGTH = 48;

	private const START_B   = 104;
	private const START_C   = 105;
	private const SWITCH_B  = 100;
	private const STOP      = 106;
	private const QUIET     = 10;
	private const MODULO    = 103;
	private const MIN_PAIRS = 2;

	/**
	 * Bar and space widths for symbol values 0-106 (106 is the stop symbol),
	 * ten per row so a value is easy to find.
	 */
	// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing.ArrayItemNoNewLine -- Lookup table.
	public const PATTERNS = array(
		'212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
		'221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
		'221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
		'212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
		'231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
		'231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
		'314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
		'112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
		'111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
		'214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
		'114131', '311141', '411131', '211412', '211214', '211232', '2331112',
	);
	// phpcs:enable WordPress.Arrays.ArrayDeclarationSpacing.ArrayItemNoNewLine

	/**
	 * Symbol values: start, data, checksum, stop.
	 *
	 * @param string $value Printable ASCII, 1-48 characters.
	 * @return int[]
	 * @throws InvalidArgumentException When the value can't be encoded.
	 */
	public static function symbols( string $value ): array {
		if ( '' === $value || strlen( $value ) > self::MAX_LENGTH || 1 !== preg_match( '/^[\x20-\x7E]+$/', $value ) ) {
			throw new InvalidArgumentException( __( 'A barcode needs 1 to 48 plain letters, digits or symbols.', 'codereyes-mobile-shop' ) );
		}

		$data = ctype_digit( $value ) && strlen( $value ) >= self::MIN_PAIRS * 2 ? self::digits( $value ) : self::text( $value );

		return array_merge( $data, array( self::checksum( $data ), self::STOP ) );
	}

	/**
	 * Narrow modules from left to right, 1 = bar and 0 = space, with quiet zones.
	 *
	 * @param string $value Value to encode.
	 * @return string
	 */
	public static function modules( string $value ): string {
		$bars = '';

		foreach ( self::symbols( $value ) as $symbol ) {
			foreach ( str_split( self::PATTERNS[ $symbol ] ) as $index => $width ) {
				$bars .= str_repeat( 0 === $index % 2 ? '1' : '0', (int) $width );
			}
		}

		$quiet = str_repeat( '0', self::QUIET );

		return $quiet . $bars . $quiet;
	}

	/**
	 * Scalable SVG: one module wide per unit, stretched to the label by CSS.
	 *
	 * @param string $value  Value to encode.
	 * @param int    $height Height in modules.
	 * @return string Safe SVG markup (contains no user text).
	 */
	public static function svg( string $value, int $height = 40 ): string {
		$modules = self::modules( $value );
		$rects   = '';

		preg_match_all( '/1+/', $modules, $runs, PREG_OFFSET_CAPTURE );

		foreach ( $runs[0] as $run ) {
			$rects .= sprintf( '<rect x="%d" width="%d" height="%d"/>', $run[1], strlen( $run[0] ), $height );
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" class="cmshop-barcode" viewBox="0 0 %1$d %2$d" preserveAspectRatio="none" shape-rendering="crispEdges" aria-hidden="true" focusable="false">%3$s</svg>',
			strlen( $modules ),
			$height,
			$rects
		);
	}

	/**
	 * Digits two at a time; an odd last digit switches to code set B.
	 *
	 * @param string $digits Digits only.
	 * @return int[]
	 */
	private static function digits( string $digits ): array {
		$data  = array( self::START_C );
		$pairs = str_split( substr( $digits, 0, strlen( $digits ) - strlen( $digits ) % 2 ), 2 );

		foreach ( $pairs as $pair ) {
			$data[] = (int) $pair;
		}

		if ( 1 === strlen( $digits ) % 2 ) {
			$data[] = self::SWITCH_B;
			$data[] = ord( substr( $digits, -1 ) ) - 32;
		}

		return $data;
	}

	/**
	 * Printable ASCII in code set B.
	 *
	 * @param string $text Text.
	 * @return int[]
	 */
	private static function text( string $text ): array {
		return array_merge(
			array( self::START_B ),
			array_map(
				static function ( string $char ): int {
					return ord( $char ) - 32;
				},
				str_split( $text )
			)
		);
	}

	/**
	 * Weighted modulo-103 check symbol.
	 *
	 * @param int[] $data Start symbol and data.
	 * @return int
	 */
	private static function checksum( array $data ): int {
		$sum = $data[0];

		foreach ( array_slice( $data, 1 ) as $position => $symbol ) {
			$sum += ( $position + 1 ) * $symbol;
		}

		return $sum % self::MODULO;
	}
}
