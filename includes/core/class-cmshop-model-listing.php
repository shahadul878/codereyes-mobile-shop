<?php
/**
 * Groups new phones of the same model into one website listing with
 * colour and storage options.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Pure rules for model listings: which phones belong together, the options
 * they make, stock and price per option, and which phone an order receives.
 *
 * Used phones are not grouped: each differs in condition and battery, so they
 * keep one listing per phone.
 */
final class Cmshop_Model_Listing {

	public const ATTRIBUTE_STORAGE = 'storage';
	public const ATTRIBUTE_COLOR   = 'color';

	/**
	 * Whether a stock row is listed as part of its model.
	 *
	 * @param array $item Item row.
	 * @return bool
	 */
	public static function is_grouped( array $item ): bool {
		return Cmshop_Item::TYPE_PHONE === ( $item['item_type'] ?? '' )
			&& 'new' === ( $item['grade'] ?? '' )
			&& '' !== self::normalize( (string) ( $item['model'] ?? '' ) );
	}

	/**
	 * Identifies a model regardless of case and spacing ("samsung|galaxy a15").
	 *
	 * @param array $item Item row.
	 * @return string
	 */
	public static function model_key( array $item ): string {
		return self::normalize( (string) ( $item['brand'] ?? '' ) ) . '|' . self::normalize( (string) ( $item['model'] ?? '' ) );
	}

	/**
	 * Identifies an option within a model ("128gb|black").
	 *
	 * @param array $item Item row.
	 * @return string
	 */
	public static function variant_key( array $item ): string {
		return self::normalize( (string) ( $item['storage'] ?? '' ) ) . '|' . self::normalize( (string) ( $item['color'] ?? '' ) );
	}

	/**
	 * Website product name for the model.
	 *
	 * @param array $item Any phone of the model.
	 * @return string
	 */
	public static function title( array $item ): string {
		return trim( self::tidy( (string) ( $item['brand'] ?? '' ) ) . ' ' . self::tidy( (string) ( $item['model'] ?? '' ) ) );
	}

	/**
	 * Options for a model, oldest first.
	 *
	 * Stock counts phones in stock (reserved and sold phones keep the option
	 * at zero). The price is that of the phone an order would receive next,
	 * or of the newest phone once the option is sold out.
	 *
	 * @param array[] $units Every grouped phone of the model, any status.
	 * @return array<int, array{key: string, storage: string, color: string, stock: int, price: string, image_id: int}>
	 */
	public static function variants( array $units ): array {
		$variants = array();

		foreach ( self::oldest_first( $units ) as $unit ) {
			$key = self::variant_key( $unit );

			if ( ! isset( $variants[ $key ] ) ) {
				$variants[ $key ] = array(
					'key'      => $key,
					'storage'  => self::tidy( (string) ( $unit['storage'] ?? '' ) ),
					'color'    => self::tidy( (string) ( $unit['color'] ?? '' ) ),
					'stock'    => 0,
					'price'    => '',
					'image_id' => 0,
				);
			}

			if ( 0 === $variants[ $key ]['image_id'] && (int) ( $unit['image_id'] ?? 0 ) > 0 ) {
				$variants[ $key ] = array_merge( $variants[ $key ], array( 'image_id' => (int) $unit['image_id'] ) );
			}

			$variants[ $key ] = self::add_unit( $variants[ $key ], $unit );
		}

		return array_values( $variants );
	}

	/**
	 * Which option attributes the model needs: storage and/or colour.
	 *
	 * An attribute no phone fills in is left out. A model needs at least one,
	 * so colour stays when both are empty (its option reads "Standard").
	 *
	 * @param array[] $variants From variants().
	 * @return string[]
	 */
	public static function attributes( array $variants ): array {
		$used = array();

		foreach ( array( self::ATTRIBUTE_STORAGE, self::ATTRIBUTE_COLOR ) as $attribute ) {
			if ( array() !== array_filter( array_column( $variants, $attribute ), 'strlen' ) ) {
				$used[] = $attribute;
			}
		}

		return array() === $used ? array( self::ATTRIBUTE_COLOR ) : $used;
	}

	/**
	 * Photos for the model's product: the first option photo leads, then the
	 * other options' photos and every phone's gallery, without repeats.
	 *
	 * @param array[] $units    Every grouped phone of the model.
	 * @param array[] $variants From variants().
	 * @return array{main: int, gallery: int[]}
	 */
	public static function images( array $units, array $variants ): array {
		$option_images = array_values( array_filter( array_map( 'intval', array_column( $variants, 'image_id' ) ) ) );
		$main          = $option_images[0] ?? 0;
		$gallery       = array_slice( $option_images, 1 );

		foreach ( self::oldest_first( $units ) as $unit ) {
			$gallery = array_merge( $gallery, Cmshop_Item::gallery_ids( (string) ( $unit['gallery_ids'] ?? '' ) ) );
		}

		$gallery = array_values( array_diff( array_unique( $gallery ), array( $main ) ) );

		return array(
			'main'    => $main,
			'gallery' => array_slice( $gallery, 0, Cmshop_Item::MAX_GALLERY ),
		);
	}

	/**
	 * Option label shown to customers; empty values read "Standard".
	 *
	 * @param string $value Storage or colour.
	 * @return string
	 */
	public static function option( string $value ): string {
		return '' === $value ? __( 'Standard', 'codereyes-mobile-shop' ) : $value;
	}

	/**
	 * The phones an order for an option receives: the oldest in stock first.
	 *
	 * @param array[] $units       Every grouped phone of the model.
	 * @param string  $variant_key Option ordered.
	 * @param int     $quantity    Phones ordered.
	 * @return int[] Item IDs; fewer than $quantity when stock is short.
	 */
	public static function allocate( array $units, string $variant_key, int $quantity ): array {
		$ids = array();

		foreach ( self::oldest_first( $units ) as $unit ) {
			if ( count( $ids ) >= $quantity ) {
				break;
			}

			if ( self::variant_key( $unit ) === $variant_key && self::in_stock( $unit ) ) {
				$ids[] = (int) $unit['id'];
			}
		}

		return $ids;
	}

	/**
	 * Count a phone towards its option and set the option price.
	 *
	 * @param array $variant Option so far.
	 * @param array $unit    Phone, visited oldest first.
	 * @return array
	 */
	private static function add_unit( array $variant, array $unit ): array {
		$price = (string) ( $unit['sale_price'] ?? '' );

		if ( self::in_stock( $unit ) ) {
			$is_first = 0 === $variant['stock'];

			return array_merge(
				$variant,
				array(
					'stock' => $variant['stock'] + 1,
					'price' => $is_first ? $price : $variant['price'],
				)
			);
		}

		// Sold-out option: follow the newest phone until one is in stock.
		return 0 === $variant['stock'] ? array_merge( $variant, array( 'price' => $price ) ) : $variant;
	}

	/**
	 * Whether a phone can be sold now.
	 *
	 * @param array $unit Phone.
	 * @return bool
	 */
	private static function in_stock( array $unit ): bool {
		return Cmshop_Item::STATUS_IN_STOCK === ( $unit['status'] ?? '' ) && (int) ( $unit['quantity'] ?? 0 ) > 0;
	}

	/**
	 * Phones sorted by ID (the order they were added), without changing the input.
	 *
	 * @param array[] $units Phones.
	 * @return array[]
	 */
	private static function oldest_first( array $units ): array {
		$sorted = array_values( $units );

		usort(
			$sorted,
			static function ( array $a, array $b ): int {
				return (int) $a['id'] <=> (int) $b['id'];
			}
		);

		return $sorted;
	}

	/**
	 * Lowercase with single spaces, for matching.
	 *
	 * @param string $value Text.
	 * @return string
	 */
	private static function normalize( string $value ): string {
		return mb_strtolower( self::tidy( $value ) );
	}

	/**
	 * Trimmed with single spaces, for display.
	 *
	 * @param string $value Text.
	 * @return string
	 */
	private static function tidy( string $value ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}
}
