<?php
/**
 * Style options for the website parts (shortcodes, blocks, Elementor widgets).
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Turns editor settings into safe CSS custom properties, and colour names
 * into swatch colours.
 */
final class Cmshop_Style {

	private const MAX_RADIUS  = 32;
	private const MAX_COLUMNS = 6;

	/**
	 * Common colour words in phone names, matched anywhere in the name.
	 * Order matters: more specific words first.
	 */
	private const SWATCHES = array(
		'mint'     => '#98e2c6',
		'lavender' => '#c4b5fd',
		'navy'     => '#1e3a8a',
		'black'    => '#111827',
		'graphite' => '#374151',
		'titanium' => '#a8a29e',
		'silver'   => '#d1d5db',
		'grey'     => '#9ca3af',
		'gray'     => '#9ca3af',
		'white'    => '#f9fafb',
		'cream'    => '#f5f0e1',
		'gold'     => '#d4af37',
		'blue'     => '#2563eb',
		'green'    => '#16a34a',
		'red'      => '#dc2626',
		'pink'     => '#f472b6',
		'purple'   => '#7c3aed',
		'violet'   => '#7c3aed',
		'yellow'   => '#facc15',
		'orange'   => '#f97316',
		'teal'     => '#0d9488',
	);

	/**
	 * A hex colour (#rgb or #rrggbb, lowercase), or '' when not one.
	 *
	 * @param string $value Colour from a setting.
	 * @return string
	 */
	public static function color( string $value ): string {
		$value = strtolower( trim( $value ) );

		return 1 === preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value ) ? $value : '';
	}

	/**
	 * Inline CSS custom properties for the valid options given.
	 *
	 * @param array $options accent (hex), radius (px, 0-32), columns (1-6).
	 * @return string e.g. "--cmshop-accent:#7c3aed;--cmshop-radius:20px".
	 */
	public static function css_vars( array $options ): string {
		$vars   = array();
		$accent = self::color( (string) ( $options['accent'] ?? '' ) );

		if ( '' !== $accent ) {
			$vars[] = '--cmshop-accent:' . $accent;
		}

		if ( self::is_number( $options['radius'] ?? '' ) ) {
			$vars[] = '--cmshop-radius:' . min( self::MAX_RADIUS, max( 0, (int) $options['radius'] ) ) . 'px';
		}

		if ( self::is_number( $options['columns'] ?? '' ) ) {
			$vars[] = '--cmshop-columns:' . min( self::MAX_COLUMNS, max( 1, (int) $options['columns'] ) );
		}

		return implode( ';', $vars );
	}

	/**
	 * Swatch colour for a colour name such as "Midnight Black", or ''.
	 *
	 * @param string $name Colour name from stock.
	 * @return string
	 */
	public static function swatch( string $name ): string {
		$name = strtolower( $name );

		foreach ( self::SWATCHES as $word => $hex ) {
			if ( false !== strpos( $name, $word ) ) {
				return $hex;
			}
		}

		return '';
	}

	/**
	 * Whether a setting is a whole number (as text or int).
	 *
	 * @param mixed $value Setting.
	 * @return bool
	 */
	private static function is_number( $value ): bool {
		return is_int( $value ) || ( is_string( $value ) && 1 === preg_match( '/^\d+$/', $value ) );
	}
}
