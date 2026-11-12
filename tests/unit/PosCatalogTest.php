<?php
/**
 * Tests for the POS product grid.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PosCatalogTest extends TestCase {

	/**
	 * A stock row.
	 *
	 * @param int   $id        Item ID.
	 * @param array $overrides Fields to change.
	 * @return array
	 */
	private function row( int $id, array $overrides = array() ): array {
		return array_merge(
			array(
				'id'             => $id,
				'item_type'      => 'phone',
				'name'           => 'Samsung Galaxy A16 128GB Black',
				'brand'          => 'Samsung',
				'model'          => 'Galaxy A16',
				'storage'        => '128GB',
				'color'          => 'Black',
				'grade'          => 'new',
				'battery_health' => null,
				'status'         => 'in_stock',
				'quantity'       => 1,
				'sale_price'     => '18500.00',
				'imei1'          => '35000000000000' . $id,
				'sku'            => '',
				'image_id'       => 0,
			),
			$overrides
		);
	}

	public function test_new_phones_become_one_tile_with_options_and_units(): void {
		$tiles = Cmshop_Pos_Catalog::build(
			array(
				$this->row( 1 ),
				$this->row( 2 ),
				$this->row( 3, array( 'color' => 'Mint', 'sale_price' => '18900.00' ) ),
			)
		);

		$this->assertCount( 1, $tiles );
		$this->assertSame( 'model', $tiles[0]['kind'] );
		$this->assertSame( 'Samsung Galaxy A16', $tiles[0]['title'] );
		$this->assertSame( 3, $tiles[0]['stock'] );
		$this->assertCount( 2, $tiles[0]['options'] );
		$this->assertSame( array( 1, 2 ), array_column( $tiles[0]['options'][0]['units'], 'id' ) );
		$this->assertSame( '#98e2c6', $tiles[0]['options'][1]['swatch'] );
		$this->assertSame( '18500.00', $tiles[0]['price_min'] );
		$this->assertSame( '18900.00', $tiles[0]['price_max'] );
	}

	public function test_used_phones_and_accessories_are_single_tiles(): void {
		$tiles = Cmshop_Pos_Catalog::build(
			array(
				$this->row( 1, array( 'grade' => 'good', 'battery_health' => 88 ) ),
				$this->row( 2, array( 'item_type' => 'accessory', 'name' => '20W charger', 'brand' => 'Anker', 'model' => '', 'quantity' => 9, 'sku' => 'ANK-20W', 'imei1' => '' ) ),
			)
		);

		$by_id = array_column( $tiles, null, 'id' );

		$this->assertSame( array( 'item', 'item' ), array_column( $tiles, 'kind' ) );
		$this->assertSame( 88, $by_id[1]['battery'] );
		$this->assertSame( 'Good', $by_id[1]['grade'] );
		$this->assertSame( 9, $by_id[2]['stock'] );
		$this->assertSame( 'ANK-20W', $by_id[2]['sku'] );
		$this->assertSame( 'accessory', $by_id[2]['type'] );
		$this->assertSame( 'used', $by_id[1]['condition'] );
		$this->assertSame( '', $by_id[2]['condition'] );
	}

	public function test_sold_and_reserved_stock_is_left_out(): void {
		$tiles = Cmshop_Pos_Catalog::build(
			array(
				$this->row( 1, array( 'status' => 'sold', 'quantity' => 0 ) ),
				$this->row( 2, array( 'status' => 'reserved', 'grade' => 'good' ) ),
			)
		);

		$this->assertSame( array(), $tiles );
	}

	public function test_brands_are_listed_once_a_to_z(): void {
		$this->assertSame(
			array( 'Apple', 'Samsung' ),
			Cmshop_Pos_Catalog::brands(
				Cmshop_Pos_Catalog::build(
					array(
						$this->row( 1 ),
						$this->row( 2, array( 'brand' => 'Apple', 'model' => 'iPhone 13', 'grade' => 'good' ) ),
						$this->row( 3, array( 'brand' => 'samsung', 'model' => 'S24' ) ),
					)
				)
			)
		);
	}

	public function test_tiles_carry_no_cost_price(): void {
		$tiles = Cmshop_Pos_Catalog::build( array( $this->row( 1, array( 'cost_price' => '15000.00' ) ) ) );

		$this->assertStringNotContainsString( '15000', (string) json_encode( $tiles ) );
	}
}
