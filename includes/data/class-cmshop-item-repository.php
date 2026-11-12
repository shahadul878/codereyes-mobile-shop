<?php
/**
 * Stock items.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table; nothing to cache across requests.
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- Only placeholder lists are interpolated.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the items table.
 */
final class Cmshop_Item_Repository extends Cmshop_Repository {

	private const SEARCH_LIMIT = 20;

	private const BRAND_LIMIT = 500;

	private const PUBLIC_LIMIT = 2000;

	/**
	 * Unsold phones that already use one of these IMEIs.
	 *
	 * A phone that was sold and bought back is a new row, so sold rows do
	 * not count as duplicates.
	 *
	 * @param string[] $imeis      IMEIs.
	 * @param int      $exclude_id Row being edited.
	 * @return array[]
	 */
	public function find_unsold_by_imeis( array $imeis, int $exclude_id = 0 ): array {
		$imeis = array_values( array_filter( $imeis ) );

		if ( array() === $imeis ) {
			return array();
		}

		$in   = implode( ', ', array_fill( 0, count( $imeis ), '%s' ) );
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM %i WHERE ( imei1 IN ( {$in} ) OR imei2 IN ( {$in} ) ) AND status <> %s AND id <> %d",
				array_merge( array( $this->table ), $imeis, $imeis, array( Cmshop_Item::STATUS_SOLD, $exclude_id ) )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Items that can be sold right now, matching a search term (POS).
	 *
	 * @param string $term Name, brand, model, IMEI or SKU.
	 * @return array[]
	 */
	public function search_sellable( string $term ): array {
		$like = '%' . $this->db->esc_like( $term ) . '%';
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT * FROM %i WHERE status <> %s AND quantity > 0
				AND ( name LIKE %s OR imei1 LIKE %s OR imei2 LIKE %s OR sku = %s OR serial = %s )
				ORDER BY item_type DESC, name ASC LIMIT %d',
				$this->table,
				Cmshop_Item::STATUS_SOLD,
				$like,
				$like,
				$like,
				$term,
				$term,
				self::SEARCH_LIMIT
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Lock rows for the rest of the transaction and return them keyed by ID.
	 *
	 * @param int[] $ids Item IDs.
	 * @return array<int,array>
	 */
	public function lock( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

		if ( array() === $ids ) {
			return array();
		}

		$in    = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$rows  = $this->db->get_results(
			$this->db->prepare( "SELECT * FROM %i WHERE id IN ( {$in} ) FOR UPDATE", array_merge( array( $this->table ), $ids ) ),
			ARRAY_A
		);
		$keyed = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$keyed[ (int) $row['id'] ] = $row;
		}

		return $keyed;
	}

	/**
	 * Add to (or, with a negative delta, take from) an item's quantity.
	 *
	 * @param array $item  Locked item row.
	 * @param int   $delta Change in quantity.
	 * @return array Updated row.
	 */
	public function adjust_quantity( array $item, int $delta ): array {
		$quantity = max( 0, (int) $item['quantity'] + $delta );
		$changes  = array(
			'quantity' => $quantity,
			'status'   => Cmshop_Item::status_for_quantity( (string) $item['status'], $quantity ),
		);

		$this->update( (int) $item['id'], $changes );

		return array_merge( $item, $changes );
	}

	/**
	 * Item linked to a WooCommerce product.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null
	 */
	public function find_by_product( int $product_id ): ?array {
		if ( $product_id <= 0 ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE wc_product_id = %d ORDER BY id DESC LIMIT 1', $this->table, $product_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Every new phone of the same model as $item (any status), oldest first.
	 *
	 * @param array $item Any phone of the model.
	 * @return array[]
	 */
	public function model_units( array $item ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT * FROM %i WHERE item_type = %s AND grade = %s AND brand = %s ORDER BY id ASC',
				$this->table,
				Cmshop_Item::TYPE_PHONE,
				'new',
				trim( (string) $item['brand'] )
			),
			ARRAY_A
		);
		$key  = Cmshop_Model_Listing::model_key( $item );

		return array_values(
			array_filter(
				is_array( $rows ) ? $rows : array(),
				static function ( array $row ) use ( $key ): bool {
					return Cmshop_Model_Listing::is_grouped( $row ) && Cmshop_Model_Listing::model_key( $row ) === $key;
				}
			)
		);
	}

	/**
	 * Point items at a WooCommerce product.
	 *
	 * @param int[] $ids        Item IDs.
	 * @param int   $product_id Product ID (0 to unlink).
	 */
	public function link_product( array $ids, int $product_id ): void {
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$this->update( $id, array( 'wc_product_id' => $product_id ) );
		}
	}

	/**
	 * IDs of the items linked to a product.
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	public function ids_for_product( int $product_id ): array {
		$ids = $this->db->get_col(
			$this->db->prepare( 'SELECT id FROM %i WHERE wc_product_id = %d', $this->table, $product_id )
		);

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Items customers can buy now, for the public stock listing.
	 *
	 * Selects public columns only: never cost prices, IMEIs or notes.
	 *
	 * @param string $type      phone|accessory|'' for both.
	 * @param string $condition new|used|'' for both (phones only).
	 * @return array[]
	 */
	public function public_stock( string $type, string $condition ): array {
		$where = array( 'status = %s', 'quantity > 0' );
		$args  = array( $this->table, Cmshop_Item::STATUS_IN_STOCK );

		if ( '' !== $type ) {
			$where[] = 'item_type = %s';
			$args[]  = $type;
		}

		if ( '' !== $condition ) {
			$where[] = 'new' === $condition ? '( item_type <> %s OR grade = %s )' : '( item_type = %s AND grade <> %s )';
			$args[]  = Cmshop_Item::TYPE_PHONE;
			$args[]  = 'new';
		}

		$args[] = self::PUBLIC_LIMIT;

		$rows = $this->db->get_results(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where holds fixed placeholders only.
				'SELECT id, item_type, name, brand, model, storage, color, grade, battery_health, status, quantity, sale_price, image_id, wc_product_id FROM %i WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d',
				$args
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Brand names used in stock, A to Z.
	 *
	 * @return string[]
	 */
	public function brands(): array {
		$names = $this->db->get_col(
			$this->db->prepare( 'SELECT DISTINCT brand FROM %i WHERE brand <> %s ORDER BY brand ASC LIMIT %d', $this->table, '', self::BRAND_LIMIT )
		);

		return array_map( 'strval', is_array( $names ) ? $names : array() );
	}

	/**
	 * Stock figures for the dashboard.
	 *
	 * @param int $low_stock Accessory quantity at or below which stock is low.
	 * @return array{phones: int, accessories: int, value: string, retail: string, low: array[]}
	 */
	public function stock_summary( int $low_stock ): array {
		$totals = $this->db->get_row(
			$this->db->prepare(
				'SELECT
					SUM( CASE WHEN item_type = %s THEN quantity ELSE 0 END ) AS phones,
					SUM( CASE WHEN item_type <> %s THEN quantity ELSE 0 END ) AS accessories,
					SUM( cost_price * quantity ) AS value,
					SUM( sale_price * quantity ) AS retail
				FROM %i WHERE status <> %s',
				Cmshop_Item::TYPE_PHONE,
				Cmshop_Item::TYPE_PHONE,
				$this->table,
				Cmshop_Item::STATUS_SOLD
			),
			ARRAY_A
		);
		$low    = $this->db->get_results(
			$this->db->prepare(
				'SELECT id, name, quantity FROM %i WHERE item_type = %s AND quantity <= %d ORDER BY quantity ASC, name ASC LIMIT 10',
				$this->table,
				Cmshop_Item::TYPE_ACCESSORY,
				$low_stock
			),
			ARRAY_A
		);

		return array(
			'phones'      => (int) ( $totals['phones'] ?? 0 ),
			'accessories' => (int) ( $totals['accessories'] ?? 0 ),
			'value'       => Cmshop_Money::from_cents( (int) round( (float) ( $totals['value'] ?? 0 ) * 100 ) ),
			'retail'      => Cmshop_Money::from_cents( (int) round( (float) ( $totals['retail'] ?? 0 ) * 100 ) ),
			'low'         => is_array( $low ) ? $low : array(),
		);
	}
}
