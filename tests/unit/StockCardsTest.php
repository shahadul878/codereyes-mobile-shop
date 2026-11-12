<?php
/**
 * Tests for the cards shown by the [cmshop_stock] shortcode.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StockCardsTest extends TestCase {

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
				'name'           => 'Samsung Galaxy A15 128GB Black',
				'grade'          => 'new',
				'brand'          => 'Samsung',
				'model'          => 'Galaxy A15',
				'storage'        => '128GB',
				'color'          => 'Black',
				'status'         => 'in_stock',
				'quantity'       => 1,
				'sale_price'     => '18500.00',
				'battery_health' => null,
				'image_id'       => 0,
				'wc_product_id'  => 0,
			),
			$overrides
		);
	}

	public function test_new_phones_of_a_model_share_one_card(): void {
		$cards = Cmshop_Stock_Cards::build(
			array(
				$this->row( 1 ),
				$this->row( 2, array( 'color' => 'Blue', 'sale_price' => '18900.00' ) ),
				$this->row( 3, array( 'storage' => '256GB', 'sale_price' => '21000.00' ) ),
			)
		);

		$this->assertCount( 1, $cards );
		$this->assertSame( 'model', $cards[0]['kind'] );
		$this->assertSame( 'Samsung Galaxy A15', $cards[0]['title'] );
		$this->assertSame( 3, $cards[0]['stock'] );
		$this->assertSame( array( 'Black', 'Blue' ), $cards[0]['colors'] );
		$this->assertSame( array( '128GB', '256GB' ), $cards[0]['storages'] );
		$this->assertSame( '18500.00', $cards[0]['price_min'] );
		$this->assertSame( '21000.00', $cards[0]['price_max'] );
	}

	public function test_used_phones_and_accessories_get_their_own_card(): void {
		$cards = Cmshop_Stock_Cards::build(
			array(
				$this->row( 1, array( 'grade' => 'good', 'battery_health' => 88, 'name' => 'Samsung Galaxy A15 Black (used)' ) ),
				$this->row( 2, array( 'grade' => 'good', 'battery_health' => 91 ) ),
				$this->row( 3, array( 'item_type' => 'accessory', 'name' => 'Charger', 'quantity' => 7, 'grade' => 'new', 'model' => '' ) ),
			)
		);

		$this->assertSame( array( 'item', 'item', 'item' ), array_column( $cards, 'kind' ) );
		$this->assertSame( 7, $cards[0]['stock'] );
		$this->assertSame( 91, $cards[1]['battery_health'] );
	}

	public function test_sold_and_reserved_items_are_left_out(): void {
		$cards = Cmshop_Stock_Cards::build(
			array(
				$this->row( 1, array( 'status' => 'sold', 'quantity' => 0 ) ),
				$this->row( 2, array( 'status' => 'reserved', 'grade' => 'good' ) ),
			)
		);

		$this->assertSame( array(), $cards );
	}

	public function test_newest_stock_comes_first(): void {
		$cards = Cmshop_Stock_Cards::build(
			array(
				$this->row( 1 ),
				$this->row( 5, array( 'grade' => 'fair', 'name' => 'Old iPhone' ) ),
				$this->row( 9, array( 'color' => 'Blue' ) ),
			)
		);

		$this->assertSame( array( 'Samsung Galaxy A15', 'Old iPhone' ), array_column( $cards, 'title' ) );
	}

	public function test_card_uses_first_photo_and_website_link_found(): void {
		$cards = Cmshop_Stock_Cards::build(
			array(
				$this->row( 1 ),
				$this->row( 2, array( 'image_id' => 40, 'wc_product_id' => 300 ) ),
				$this->row( 3, array( 'image_id' => 41 ) ),
			)
		);

		$this->assertSame( 40, $cards[0]['image_id'] );
		$this->assertSame( 300, $cards[0]['product_id'] );
	}

	public function test_cards_never_carry_cost_or_imei(): void {
		$cards = Cmshop_Stock_Cards::build( array( $this->row( 1, array( 'imei1' => '490154203237518', 'cost_price' => '15000.00' ) ) ) );

		$this->assertStringNotContainsString( '490154203237518', (string) wp_json_encode_stub( $cards ) );
		$this->assertStringNotContainsString( '15000', (string) wp_json_encode_stub( $cards ) );
	}

	public function test_page_slices_cards(): void {
		$cards = array_map(
			function ( int $id ): array {
				return array( 'id' => $id );
			},
			range( 1, 30 )
		);

		$page = Cmshop_Stock_Cards::page( $cards, 2, 12 );

		$this->assertSame( 13, $page['cards'][0]['id'] );
		$this->assertCount( 12, $page['cards'] );
		$this->assertSame( 3, $page['pages'] );
		$this->assertSame( 2, $page['page'] );
		$this->assertSame( 3, Cmshop_Stock_Cards::page( $cards, 99, 12 )['page'] );
	}
}

/**
 * JSON for assertions without WordPress.
 *
 * @param mixed $data Data.
 * @return string|false
 */
function wp_json_encode_stub( $data ) {
	return json_encode( $data );
}
