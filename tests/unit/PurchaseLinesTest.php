<?php
/**
 * Bulk purchase lines: phones by scanned IMEIs, accessories by quantity.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PurchaseLinesTest extends TestCase {

	private const A = '490154203237518';
	private const B = '356938035643809';
	private const C = '352099001761481';

	private function phone( array $over = array() ): array {
		return array_merge(
			array(
				'type'       => 'phone',
				'brand'      => 'Samsung',
				'model'      => 'Galaxy A15',
				'storage'    => '128GB',
				'color'      => 'Black',
				'grade'      => 'new',
				'cost_price' => '15,000',
				'sale_price' => '18500',
				'imeis'      => self::A . "\n" . self::B . "\n",
			),
			$over
		);
	}

	private function accessory( array $over = array() ): array {
		return array_merge(
			array(
				'type'       => 'accessory',
				'item_id'    => '0',
				'name'       => 'USB-C cable',
				'sku'        => 'CB-1',
				'quantity'   => '20',
				'cost_price' => '120',
				'sale_price' => '350',
			),
			$over
		);
	}

	public function test_phone_line_becomes_one_unit_per_imei(): void {
		$parsed = Cmshop_Purchase_Lines::parse( array( $this->phone() ) );

		$this->assertSame( array(), $parsed['errors'] );
		$this->assertSame( array( self::A, self::B ), $parsed['lines'][0]['imeis'] );
		$this->assertSame( 2, $parsed['lines'][0]['quantity'] );
		$this->assertSame( 1500000, $parsed['lines'][0]['cost'] );
		$this->assertSame( 3000000, $parsed['lines'][0]['total'] );
		$this->assertSame( 2, $parsed['units'] );
		$this->assertSame( 3000000, $parsed['total'] );
	}

	public function test_imeis_may_be_separated_by_commas_and_contain_spaces_or_bangla_digits(): void {
		$parsed = Cmshop_Purchase_Lines::parse( array( $this->phone( array( 'imeis' => '490154 203237 518, ৩৫৬৯৩৮০৩৫৬৪৩৮০৯;' . self::C ) ) ) );

		$this->assertSame( array( self::A, self::B, self::C ), $parsed['lines'][0]['imeis'] );
	}

	public function test_bad_and_repeated_imeis_are_reported_with_the_line(): void {
		$parsed = Cmshop_Purchase_Lines::parse(
			array(
				$this->phone( array( 'imeis' => self::A . "\n490154203237519" ) ),
				$this->phone( array( 'model' => 'Galaxy A25', 'imeis' => self::A ) ),
			)
		);

		$this->assertStringContainsString( '490154203237519', $parsed['errors']['lines-0-imeis'] );
		$this->assertStringContainsString( self::A, $parsed['errors']['lines-1-imeis'] );
	}

	public function test_phone_needs_brand_model_and_an_imei(): void {
		$parsed = Cmshop_Purchase_Lines::parse( array( $this->phone( array( 'brand' => '', 'model' => '', 'imeis' => '' ) ) ) );

		$this->assertArrayHasKey( 'lines-0-brand', $parsed['errors'] );
		$this->assertArrayHasKey( 'lines-0-model', $parsed['errors'] );
		$this->assertArrayHasKey( 'lines-0-imeis', $parsed['errors'] );
	}

	public function test_accessory_line_uses_its_quantity(): void {
		$parsed = Cmshop_Purchase_Lines::parse( array( $this->accessory() ) );

		$this->assertSame( array(), $parsed['errors'] );
		$this->assertSame( 20, $parsed['lines'][0]['quantity'] );
		$this->assertSame( 240000, $parsed['total'] );
		$this->assertSame( 0, $parsed['lines'][0]['item_id'] );
	}

	public function test_restocking_an_existing_accessory_needs_no_name(): void {
		$parsed = Cmshop_Purchase_Lines::parse( array( $this->accessory( array( 'item_id' => '42', 'name' => '' ) ) ) );

		$this->assertSame( array(), $parsed['errors'] );
		$this->assertSame( 42, $parsed['lines'][0]['item_id'] );
	}

	public function test_accessory_needs_a_name_and_a_quantity(): void {
		$parsed = Cmshop_Purchase_Lines::parse( array( $this->accessory( array( 'name' => '', 'quantity' => '0' ) ) ) );

		$this->assertArrayHasKey( 'lines-0-name', $parsed['errors'] );
		$this->assertArrayHasKey( 'lines-0-quantity', $parsed['errors'] );
	}

	public function test_bad_prices_are_reported(): void {
		$parsed = Cmshop_Purchase_Lines::parse( array( $this->accessory( array( 'cost_price' => 'abc', 'sale_price' => '' ) ) ) );

		$this->assertArrayHasKey( 'lines-0-cost_price', $parsed['errors'] );
		$this->assertArrayHasKey( 'lines-0-sale_price', $parsed['errors'] );
	}

	public function test_blank_lines_are_skipped_and_an_empty_purchase_is_refused(): void {
		$blank  = array(
			'type'  => 'phone',
			'brand' => '',
			'imeis' => '',
			'grade' => 'new',
		);
		$parsed = Cmshop_Purchase_Lines::parse( array( $blank, $this->accessory(), array( 'type' => 'accessory', 'item_id' => '0', 'quantity' => '' ) ) );

		$this->assertSame( array(), $parsed['errors'] );
		$this->assertCount( 1, $parsed['lines'] );
		$this->assertArrayHasKey( 'lines', Cmshop_Purchase_Lines::parse( array( $blank ) )['errors'] );
	}

	public function test_size_limits(): void {
		$many = array_fill( 0, Cmshop_Purchase_Lines::MAX_LINES + 1, $this->accessory() );

		$this->assertArrayHasKey( 'lines', Cmshop_Purchase_Lines::parse( $many )['errors'] );
		$this->assertArrayHasKey( 'lines-0-quantity', Cmshop_Purchase_Lines::parse( array( $this->accessory( array( 'quantity' => '100000' ) ) ) )['errors'] );
	}

	public function test_item_fields_for_the_stock_validator(): void {
		$line = Cmshop_Purchase_Lines::parse( array( $this->phone() ) )['lines'][0];

		$this->assertSame( 'Samsung', $line['fields']['brand'] );
		$this->assertSame( '15000.00', $line['fields']['cost_price'] );
		$this->assertSame( '18500.00', $line['fields']['sale_price'] );
		$this->assertSame( 'phone', $line['fields']['item_type'] );
	}
}
