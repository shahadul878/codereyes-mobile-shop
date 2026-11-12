<?php
/**
 * Block attributes → shortcode attributes.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Blocks store camelCase, typed attributes; the shortcode renderers take the
 * same options as text. Defaults are left out so the renderer's own apply.
 */
final class Cmshop_Block_Atts {

	/**
	 * Shortcode attributes for a block.
	 *
	 * @param string $part       stock|warranty|repair_request|repair_status.
	 * @param array  $attributes Block attributes.
	 * @return array<string,string>
	 */
	public static function map( string $part, array $attributes ): array {
		$atts = 'stock' === $part ? self::stock( $attributes ) : array();

		if ( '' !== (string) ( $attributes['accentColor'] ?? '' ) ) {
			$atts['accent'] = (string) $attributes['accentColor'];
		}

		if ( isset( $attributes['cornerRadius'] ) && is_numeric( $attributes['cornerRadius'] ) ) {
			$atts['radius'] = (string) (int) $attributes['cornerRadius'];
		}

		return $atts;
	}

	/**
	 * Stock listing options.
	 *
	 * @param array $attributes Block attributes.
	 * @return array<string,string>
	 */
	private static function stock( array $attributes ): array {
		$atts = array(
			'type'      => (string) ( $attributes['type'] ?? 'phone' ),
			'brand'     => (string) ( $attributes['brand'] ?? '' ),
			'condition' => (string) ( $attributes['condition'] ?? 'all' ),
			'per_page'  => (string) (int) ( $attributes['perPage'] ?? 12 ),
			'filters'   => false === ( $attributes['filters'] ?? true ) ? 'no' : 'yes',
		);

		if ( (int) ( $attributes['columns'] ?? 0 ) > 0 ) {
			$atts['columns'] = (string) (int) $attributes['columns'];
		}

		return $atts;
	}
}
