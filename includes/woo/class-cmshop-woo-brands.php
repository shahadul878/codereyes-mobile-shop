<?php
/**
 * Keeps stock brands in step with WooCommerce Brands (product_brand).
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Assigns published products to WooCommerce brands, creating a brand the
 * first time it is used, and lists existing brands for the stock form.
 */
final class Cmshop_Woo_Brands {

	public const TAXONOMY = 'product_brand';

	private const MAX_SUGGESTIONS = 500;

	/**
	 * Whether WooCommerce Brands is available (WooCommerce 9.6+).
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return taxonomy_exists( self::TAXONOMY );
	}

	/**
	 * Put a product under its brand. An empty brand leaves the product as it is.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $brand      Brand name from stock.
	 */
	public static function assign( int $product_id, string $brand ): void {
		$brand = trim( $brand );

		if ( $product_id <= 0 || '' === $brand || ! self::is_available() ) {
			return;
		}

		$term_id = self::term_id( $brand );

		if ( $term_id > 0 ) {
			wp_set_object_terms( $product_id, array( $term_id ), self::TAXONOMY );
		}
	}

	/**
	 * Brand names already in WooCommerce, for suggestions.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		if ( ! self::is_available() ) {
			return array();
		}

		$names = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'names',
				'number'     => self::MAX_SUGGESTIONS,
			)
		);

		return is_array( $names ) ? array_map( 'strval', $names ) : array();
	}

	/**
	 * Existing brand (matched by name, ignoring case) or a new one.
	 *
	 * @param string $brand Brand name.
	 * @return int Term ID, 0 on failure.
	 */
	private static function term_id( string $brand ): int {
		$term = get_term_by( 'name', $brand, self::TAXONOMY );

		if ( $term instanceof WP_Term ) {
			return (int) $term->term_id;
		}

		$created = wp_insert_term( $brand, self::TAXONOMY );

		if ( is_wp_error( $created ) ) {
			// A brand with the same slug already exists: use it.
			$existing = $created->get_error_data( 'term_exists' );
			return is_numeric( $existing ) ? (int) $existing : 0;
		}

		return (int) $created['term_id'];
	}
}
