<?php
/**
 * Sanitising of submitted data that arrives as nested arrays.
 *
 * @package CodereyesMobileShopManager
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Cleans whole forms and JSON payloads as soon as they are received, so
 * nothing raw is stored, even for a moment. Validators then narrow each field.
 */
final class Cmshop_Sanitize {

	public const MAX_DEPTH = 8;

	private const KEY_PATTERN = '/^[A-Za-z0-9_\-]{1,64}$/';

	/**
	 * Strings become plain text (tags stripped, line breaks kept); numbers,
	 * booleans and null keep their type; unsafe keys, other types and anything
	 * nested deeper than MAX_DEPTH are dropped.
	 *
	 * @param mixed $value Raw value (already unslashed).
	 * @param int   $depth Current depth.
	 * @return mixed
	 */
	public static function deep( $value, int $depth = 0 ) {
		if ( is_array( $value ) ) {
			return $depth >= self::MAX_DEPTH ? array() : self::items( $value, $depth );
		}

		if ( is_string( $value ) ) {
			return sanitize_textarea_field( $value );
		}

		return is_int( $value ) || is_float( $value ) || is_bool( $value ) || null === $value ? $value : null;
	}

	/**
	 * Sanitise each entry of an array, keeping only safe keys.
	 *
	 * @param array $values Values.
	 * @param int   $depth  Depth of this array.
	 * @return array
	 */
	private static function items( array $values, int $depth ): array {
		$clean = array();

		foreach ( $values as $key => $item ) {
			if ( is_int( $key ) || 1 === preg_match( self::KEY_PATTERN, $key ) ) {
				$clean[ $key ] = self::deep( $item, $depth + 1 );
			}
		}

		return $clean;
	}
}
