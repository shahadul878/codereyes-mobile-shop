<?php
/**
 * The point-of-sale product grid.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Builds POS tiles from sellable stock: one tile per new-phone model with
 * its colour/storage options and the phones (IMEIs) under each, and one tile
 * per used phone or accessory. Never carries cost prices.
 */
final class Cmshop_Pos_Catalog {

	/**
	 * Tiles for stock that can be sold now, newest first.
	 *
	 * @param array[] $rows Stock rows (id, item_type, name, brand, model, storage, color, grade,
	 *                      battery_health, status, quantity, sale_price, imei1, sku, image_id).
	 * @return array[]
	 */
	public static function build( array $rows ): array {
		$tiles = array();

		foreach ( self::oldest_first( $rows ) as $row ) {
			if ( Cmshop_Item::STATUS_IN_STOCK !== $row['status'] || (int) $row['quantity'] <= 0 ) {
				continue;
			}

			if ( Cmshop_Model_Listing::is_grouped( $row ) ) {
				$key           = 'model:' . Cmshop_Model_Listing::model_key( $row );
				$tiles[ $key ] = self::add_unit( $tiles[ $key ] ?? self::model_tile( $key, $row ), $row );
				continue;
			}

			$tiles[ 'item:' . (int) $row['id'] ] = self::item_tile( $row );
		}

		$tiles = array_map(
			static function ( array $tile ): array {
				return 'model' === $tile['kind'] ? array_merge( $tile, array( 'options' => array_values( $tile['options'] ) ) ) : $tile;
			},
			array_values( $tiles )
		);

		usort(
			$tiles,
			static function ( array $a, array $b ): int {
				return $b['newest'] <=> $a['newest'];
			}
		);

		return $tiles;
	}

	/**
	 * Brands among the tiles, A to Z, each once regardless of case.
	 *
	 * @param array[] $tiles Tiles.
	 * @return string[]
	 */
	public static function brands( array $tiles ): array {
		$brands = array();

		// Oldest first, so a brand keeps the spelling it was first entered with.
		foreach ( array_reverse( $tiles ) as $tile ) {
			$name = trim( (string) $tile['brand'] );

			if ( '' !== $name ) {
				$brands[ mb_strtolower( $name ) ] = $brands[ mb_strtolower( $name ) ] ?? $name;
			}
		}

		natcasesort( $brands );

		return array_values( $brands );
	}

	/**
	 * Empty tile for a new-phone model.
	 *
	 * @param string $key Tile key.
	 * @param array  $row First phone of the model.
	 * @return array
	 */
	private static function model_tile( string $key, array $row ): array {
		return array(
			'key'       => $key,
			'kind'      => 'model',
			'type'      => Cmshop_Item::TYPE_PHONE,
			'condition' => 'new',
			'title'     => Cmshop_Model_Listing::title( $row ),
			'brand'     => trim( (string) $row['brand'] ),
			'grade'     => Cmshop_Item::label( Cmshop_Item::grades(), 'new' ),
			'image_id'  => 0,
			'stock'     => 0,
			'price_min' => (string) $row['sale_price'],
			'price_max' => (string) $row['sale_price'],
			'options'   => array(),
			'newest'    => 0,
		);
	}

	/**
	 * Add one phone to its model tile, under its storage/colour option.
	 *
	 * @param array $tile Model tile.
	 * @param array $row  Phone.
	 * @return array
	 */
	private static function add_unit( array $tile, array $row ): array {
		$price   = (string) $row['sale_price'];
		$options = $tile['options'];
		$key     = Cmshop_Model_Listing::variant_key( $row );

		if ( ! isset( $options[ $key ] ) ) {
			$options[ $key ] = array(
				'key'     => $key,
				'storage' => trim( (string) $row['storage'] ),
				'color'   => trim( (string) $row['color'] ),
				'swatch'  => Cmshop_Style::swatch( (string) $row['color'] ),
				'units'   => array(),
			);
		}

		$options[ $key ]['units'][] = array(
			'id'    => (int) $row['id'],
			'imei'  => (string) $row['imei1'],
			'price' => $price,
		);

		return array_merge(
			$tile,
			array(
				'options'   => $options,
				'stock'     => $tile['stock'] + 1,
				'price_min' => Cmshop_Money::to_cents( $price ) < Cmshop_Money::to_cents( $tile['price_min'] ) ? $price : $tile['price_min'],
				'price_max' => Cmshop_Money::to_cents( $price ) > Cmshop_Money::to_cents( $tile['price_max'] ) ? $price : $tile['price_max'],
				'image_id'  => 0 === $tile['image_id'] ? (int) ( $row['image_id'] ?? 0 ) : $tile['image_id'],
				'newest'    => max( $tile['newest'], (int) $row['id'] ),
			)
		);
	}

	/**
	 * Tile for a used phone or an accessory.
	 *
	 * @param array $row Stock row.
	 * @return array
	 */
	private static function item_tile( array $row ): array {
		$is_phone = Cmshop_Item::TYPE_PHONE === $row['item_type'];

		return array(
			'key'       => 'item:' . (int) $row['id'],
			'kind'      => 'item',
			'id'        => (int) $row['id'],
			'type'      => (string) $row['item_type'],
			'condition' => $is_phone ? ( 'new' === $row['grade'] ? 'new' : 'used' ) : '',
			'title'     => $is_phone && '' !== trim( (string) $row['model'] ) ? Cmshop_Model_Listing::title( $row ) : (string) $row['name'],
			'detail'    => $is_phone ? trim( $row['storage'] . ' ' . $row['color'] ) : '',
			'brand'     => trim( (string) $row['brand'] ),
			'grade'     => $is_phone ? Cmshop_Item::label( Cmshop_Item::grades(), (string) $row['grade'] ) : '',
			'battery'   => null === $row['battery_health'] || '' === $row['battery_health'] ? null : (int) $row['battery_health'],
			'imei'      => (string) $row['imei1'],
			'sku'       => (string) $row['sku'],
			'image_id'  => (int) ( $row['image_id'] ?? 0 ),
			'stock'     => (int) $row['quantity'],
			'price'     => (string) $row['sale_price'],
			'newest'    => (int) $row['id'],
		);
	}

	/**
	 * Rows sorted by ID, without changing the input.
	 *
	 * @param array[] $rows Rows.
	 * @return array[]
	 */
	private static function oldest_first( array $rows ): array {
		$sorted = array_values( $rows );

		usort(
			$sorted,
			static function ( array $a, array $b ): int {
				return (int) $a['id'] <=> (int) $b['id'];
			}
		);

		return $sorted;
	}
}
