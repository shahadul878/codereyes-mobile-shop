<?php
/**
 * Price sticker contents.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StickersTest extends TestCase {

	private function phone( array $over = array() ): array {
		return array_merge(
			array(
				'id'             => 7,
				'item_type'      => 'phone',
				'name'           => '',
				'brand'          => 'Samsung',
				'model'          => 'Galaxy A15',
				'storage'        => '128GB',
				'color'          => 'Black',
				'grade'          => 'good',
				'battery_health' => '91',
				'imei1'          => '356938035643809',
				'sku'            => '',
				'quantity'       => '1',
				'sale_price'     => '18500.00',
				'cost_price'     => '15000.00',
			),
			$over
		);
	}

	private function cable( array $over = array() ): array {
		return array_merge(
			$this->phone(),
			array(
				'id'             => 42,
				'item_type'      => 'accessory',
				'name'           => 'USB-C cable',
				'brand'          => '',
				'model'          => '',
				'storage'        => '',
				'color'          => '',
				'grade'          => '',
				'battery_health' => null,
				'imei1'          => '',
				'quantity'       => '4',
				'sale_price'     => '350.00',
			),
			$over
		);
	}

	public function test_phone_code_is_its_imei(): void {
		$this->assertSame( '356938035643809', Cmshop_Stickers::code( $this->phone() ) );
	}

	public function test_accessory_code_is_sku_or_item_number(): void {
		$this->assertSame( 'CB-USBC-1M', Cmshop_Stickers::code( $this->cable( array( 'sku' => 'CB-USBC-1M' ) ) ) );
		$this->assertSame( 'CMS000042', Cmshop_Stickers::code( $this->cable() ) );
		// A SKU a scanner can't print falls back to the item number.
		$this->assertSame( 'CMS000042', Cmshop_Stickers::code( $this->cable( array( 'sku' => 'ক্যাবল' ) ) ) );
		// A SKU too long to scan on a 38 mm label falls back too.
		$this->assertSame( 'CMS000042', Cmshop_Stickers::code( $this->cable( array( 'sku' => 'ANKER-POWERLINE-III-USB-C' ) ) ) );
	}

	public function test_item_number_round_trips(): void {
		$this->assertSame( 42, Cmshop_Stickers::id_from_code( 'cms000042' ) );
		$this->assertNull( Cmshop_Stickers::id_from_code( 'CMS' ) );
		$this->assertNull( Cmshop_Stickers::id_from_code( '356938035643809' ) );
	}

	public function test_phone_label_has_model_variant_condition_and_price_but_never_cost(): void {
		$label = Cmshop_Stickers::label( $this->phone() );

		$this->assertSame( 'Samsung Galaxy A15', $label['title'] );
		$this->assertSame( '128GB · Black', $label['detail'] );
		$this->assertSame( 'Good · Battery 91%', $label['condition'] );
		$this->assertSame( '18500.00', $label['price'] );
		$this->assertSame( '356938035643809', $label['code'] );
		$this->assertStringNotContainsString( '15000', (string) json_encode( $label ) );
	}

	public function test_new_phone_condition_reads_new(): void {
		$label = Cmshop_Stickers::label( $this->phone( array( 'grade' => 'new', 'battery_health' => null ) ) );

		$this->assertSame( 'New (sealed)', $label['condition'] );
	}

	public function test_accessory_label_uses_its_name(): void {
		$label = Cmshop_Stickers::label( $this->cable() );

		$this->assertSame( 'USB-C cable', $label['title'] );
		$this->assertSame( '', $label['detail'] );
		$this->assertSame( '', $label['condition'] );
	}

	public function test_copies_per_item_or_one_per_unit_in_stock(): void {
		$items = array( $this->phone(), $this->cable() );

		$this->assertCount( 2, Cmshop_Stickers::sheet( $items, '1' ) );
		$this->assertCount( 4, Cmshop_Stickers::sheet( $items, '2' ) );
		// Phones are one unit each; the cable has 4 in stock.
		$this->assertCount( 5, Cmshop_Stickers::sheet( $items, 'stock' ) );
		// Unknown choices print one each.
		$this->assertCount( 2, Cmshop_Stickers::sheet( $items, '99' ) );
	}

	public function test_sheet_is_capped(): void {
		$many = $this->cable( array( 'quantity' => '5000' ) );

		$this->assertCount( Cmshop_Stickers::MAX_LABELS, Cmshop_Stickers::sheet( array( $many ), 'stock' ) );
	}

	public function test_unknown_size_falls_back_to_the_small_roll(): void {
		$this->assertSame( '38x25', Cmshop_Stickers::size( 'huge' ) );
		$this->assertSame( 'a4', Cmshop_Stickers::size( 'a4' ) );
		$this->assertArrayHasKey( '50x30', Cmshop_Stickers::sizes() );
	}
}

