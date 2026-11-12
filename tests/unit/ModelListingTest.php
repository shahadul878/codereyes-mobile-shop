<?php
/**
 * Tests for grouping new phones of one model into website options.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ModelListingTest extends TestCase {

	/**
	 * A stock row for a phone.
	 *
	 * @param int   $id        Item ID.
	 * @param array $overrides Fields to change.
	 * @return array
	 */
	private function unit( int $id, array $overrides = array() ): array {
		return array_merge(
			array(
				'id'         => $id,
				'item_type'  => 'phone',
				'grade'      => 'new',
				'brand'      => 'Samsung',
				'model'      => 'Galaxy A15',
				'storage'    => '128GB',
				'color'      => 'Black',
				'status'     => 'in_stock',
				'quantity'   => 1,
				'sale_price' => '18500.00',
			),
			$overrides
		);
	}

	public function test_only_new_phones_are_grouped(): void {
		$this->assertTrue( Cmshop_Model_Listing::is_grouped( $this->unit( 1 ) ) );
		$this->assertFalse( Cmshop_Model_Listing::is_grouped( $this->unit( 1, array( 'grade' => 'like_new' ) ) ) );
		$this->assertFalse( Cmshop_Model_Listing::is_grouped( $this->unit( 1, array( 'item_type' => 'accessory' ) ) ) );
		$this->assertFalse( Cmshop_Model_Listing::is_grouped( $this->unit( 1, array( 'model' => '  ' ) ) ) );
	}

	public function test_model_key_ignores_case_and_extra_spaces(): void {
		$this->assertSame(
			Cmshop_Model_Listing::model_key( $this->unit( 1 ) ),
			Cmshop_Model_Listing::model_key( $this->unit( 2, array( 'brand' => ' samsung ', 'model' => 'GALAXY   a15' ) ) )
		);
		$this->assertNotSame(
			Cmshop_Model_Listing::model_key( $this->unit( 1 ) ),
			Cmshop_Model_Listing::model_key( $this->unit( 2, array( 'model' => 'Galaxy A25' ) ) )
		);
	}

	public function test_title_is_brand_and_model(): void {
		$this->assertSame( 'Samsung Galaxy A15', Cmshop_Model_Listing::title( $this->unit( 1 ) ) );
	}

	public function test_counts_in_stock_phones_per_colour_and_storage(): void {
		$variants = Cmshop_Model_Listing::variants(
			array(
				$this->unit( 1 ),
				$this->unit( 2 ),
				$this->unit( 3, array( 'color' => 'Blue' ) ),
				$this->unit( 4, array( 'storage' => '256GB' ) ),
				$this->unit( 5, array( 'color' => 'black ' ) ),
			)
		);

		$stock = array_column( $variants, 'stock', 'key' );

		$this->assertCount( 3, $variants );
		$this->assertSame( 3, $stock[ Cmshop_Model_Listing::variant_key( $this->unit( 1 ) ) ] );
		$this->assertSame( 1, $stock[ Cmshop_Model_Listing::variant_key( $this->unit( 3, array( 'color' => 'Blue' ) ) ) ] );
	}

	public function test_sold_and_reserved_phones_keep_the_option_at_zero_stock(): void {
		$variants = Cmshop_Model_Listing::variants(
			array(
				$this->unit( 1, array( 'status' => 'sold', 'quantity' => 0 ) ),
				$this->unit( 2, array( 'color' => 'Blue', 'status' => 'reserved' ) ),
			)
		);

		$this->assertSame( array( 0, 0 ), array_column( $variants, 'stock' ) );
	}

	public function test_price_comes_from_the_phone_that_sells_next(): void {
		$variants = Cmshop_Model_Listing::variants(
			array(
				$this->unit( 7, array( 'sale_price' => '19000.00' ) ),
				$this->unit( 3, array( 'sale_price' => '18000.00', 'status' => 'sold', 'quantity' => 0 ) ),
				$this->unit( 5, array( 'sale_price' => '18500.00' ) ),
			)
		);

		$this->assertSame( '18500.00', $variants[0]['price'] );
	}

	public function test_price_of_a_sold_out_option_is_the_latest_phone(): void {
		$variants = Cmshop_Model_Listing::variants(
			array(
				$this->unit( 1, array( 'sale_price' => '18000.00', 'status' => 'sold', 'quantity' => 0 ) ),
				$this->unit( 2, array( 'sale_price' => '18800.00', 'status' => 'sold', 'quantity' => 0 ) ),
			)
		);

		$this->assertSame( '18800.00', $variants[0]['price'] );
	}

	public function test_option_labels_use_the_oldest_spelling(): void {
		$variants = Cmshop_Model_Listing::variants(
			array(
				$this->unit( 2, array( 'color' => 'BLACK' ) ),
				$this->unit( 1, array( 'color' => 'Black' ) ),
			)
		);

		$this->assertSame( 'Black', $variants[0]['color'] );
	}

	public function test_attributes_skip_storage_when_no_phone_has_it(): void {
		$variants = Cmshop_Model_Listing::variants(
			array(
				$this->unit( 1, array( 'storage' => '' ) ),
				$this->unit( 2, array( 'storage' => '', 'color' => 'Blue' ) ),
			)
		);

		$this->assertSame( array( 'color' ), Cmshop_Model_Listing::attributes( $variants ) );
	}

	public function test_attributes_fall_back_to_colour_when_nothing_is_set(): void {
		$variants = Cmshop_Model_Listing::variants( array( $this->unit( 1, array( 'storage' => '', 'color' => '' ) ) ) );

		$this->assertSame( array( 'color' ), Cmshop_Model_Listing::attributes( $variants ) );
		$this->assertSame( 'Standard', Cmshop_Model_Listing::option( '' ) );
	}

	public function test_attributes_list_storage_before_colour(): void {
		$variants = Cmshop_Model_Listing::variants( array( $this->unit( 1 ) ) );

		$this->assertSame( array( 'storage', 'color' ), Cmshop_Model_Listing::attributes( $variants ) );
	}

	public function test_allocates_the_oldest_in_stock_phones_of_the_option(): void {
		$units = array(
			$this->unit( 9 ),
			$this->unit( 4, array( 'color' => 'Blue' ) ),
			$this->unit( 6 ),
			$this->unit( 2, array( 'status' => 'sold', 'quantity' => 0 ) ),
			$this->unit( 7 ),
		);

		$key = Cmshop_Model_Listing::variant_key( $this->unit( 1 ) );

		$this->assertSame( array( 6, 7 ), Cmshop_Model_Listing::allocate( $units, $key, 2 ) );
	}

	public function test_allocation_returns_fewer_when_stock_is_short(): void {
		$key = Cmshop_Model_Listing::variant_key( $this->unit( 1 ) );

		$this->assertSame( array( 1 ), Cmshop_Model_Listing::allocate( array( $this->unit( 1 ) ), $key, 3 ) );
		$this->assertSame( array(), Cmshop_Model_Listing::allocate( array( $this->unit( 1 ) ), 'nope|nope', 1 ) );
	}

	public function test_each_option_takes_the_first_photo_of_its_phones(): void {
		$variants = Cmshop_Model_Listing::variants(
			array(
				$this->unit( 1, array( 'image_id' => 0 ) ),
				$this->unit( 2, array( 'image_id' => 50 ) ),
				$this->unit( 3, array( 'image_id' => 51 ) ),
				$this->unit( 4, array( 'color' => 'Blue', 'image_id' => 60 ) ),
				$this->unit( 5, array( 'color' => 'Green' ) ),
			)
		);

		$this->assertSame( array( 50, 60, 0 ), array_column( $variants, 'image_id' ) );
	}

	public function test_model_photos_use_the_first_option_photo_then_the_rest(): void {
		$units    = array(
			$this->unit( 1, array( 'image_id' => 50, 'gallery_ids' => '70,71' ) ),
			$this->unit( 2, array( 'color' => 'Blue', 'image_id' => 60, 'gallery_ids' => '71,72' ) ),
			$this->unit( 3, array( 'color' => 'Green', 'gallery_ids' => '50' ) ),
		);
		$images   = Cmshop_Model_Listing::images( $units, Cmshop_Model_Listing::variants( $units ) );

		$this->assertSame( 50, $images['main'] );
		$this->assertSame( array( 60, 70, 71, 72 ), $images['gallery'] );
	}

	public function test_model_without_photos_has_none(): void {
		$units  = array( $this->unit( 1 ) );
		$images = Cmshop_Model_Listing::images( $units, Cmshop_Model_Listing::variants( $units ) );

		$this->assertSame( array( 'main' => 0, 'gallery' => array() ), $images );
	}
}
