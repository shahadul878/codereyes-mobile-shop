<?php
/**
 * Tests for the small helpers: money, IMEI, phone numbers, codes, CSV, items.
 *
 * @package CodereyesMobileShop
 */

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase {

	/**
	 * @dataProvider money_inputs
	 */
	public function test_money_parse( string $raw, ?string $expected ): void {
		$this->assertSame( $expected, Cmshop_Money::parse( $raw ) );
	}

	public function money_inputs(): array {
		return array(
			array( '12500', '12500.00' ),
			array( '1,50,000.5', '150000.50' ),
			array( '৳ ১৫,০০০', '15000.00' ),
			array( '£20.99', '20.99' ),
			array( '500 BDT', '500.00' ),
			array( '-5', null ),
			array( '1,5', null ),
			array( '1.999', null ),
			array( '99999999999', null ),
			array( '', null ),
		);
	}

	public function test_money_round_trip_and_format(): void {
		$this->assertSame( -30025, Cmshop_Money::to_cents( '-300.25' ) );
		$this->assertSame( '-300.25', Cmshop_Money::from_cents( -30025 ) );
		$this->assertSame( '৳12,500.50', Cmshop_Money::format( '12500.50', '৳' ) );
		$this->assertSame( '-৳5.00', Cmshop_Money::format( '-5.00', '৳' ) );
		$this->assertSame( '—', Cmshop_Money::format( null, '৳' ) );
		$this->assertSame( '0.00', Cmshop_Money::parse_or_zero( '  ' ) );
	}

	public function test_imei(): void {
		$this->assertTrue( Cmshop_Imei::is_valid( Cmshop_Imei::normalize( '49-015420-323751-8' ) ) );
		$this->assertFalse( Cmshop_Imei::is_valid( '490154203237519' ) );
		$this->assertFalse( Cmshop_Imei::is_valid( '000000000000000' ) );
		$this->assertFalse( Cmshop_Imei::is_valid( '49015420323751A' ) );
	}

	public function test_phone_number(): void {
		$this->assertSame( '+8801712345678', Cmshop_Phone_Number::normalize( ' +880 1712-345678 ' ) );
		$this->assertSame( '01712', Cmshop_Phone_Number::normalize( '০১৭১২' ) );
		$this->assertFalse( Cmshop_Phone_Number::is_valid( '01712' ) );
		$this->assertTrue( Cmshop_Phone_Number::is_valid( '01712345678' ) );
		$this->assertSame( '5678', Cmshop_Phone_Number::last_four( '+8801712345678' ) );
	}

	public function test_codes(): void {
		$this->assertSame( 'INV-000042', Cmshop_Code::invoice( 'INV-', 42 ) );

		$ticket = Cmshop_Code::ticket();
		$this->assertTrue( Cmshop_Code::is_ticket( $ticket ) );
		$this->assertSame( 'K7MQ2XRT', Cmshop_Code::normalize_ticket( ' k7mq-2xrt ' ) );
		$this->assertFalse( Cmshop_Code::is_ticket( 'K7MQ2XR0' ), 'zero is not in the alphabet' );
		$this->assertFalse( Cmshop_Code::is_ticket( 'K7MQ2X' ) );
	}

	public function test_csv_cells(): void {
		$this->assertSame( "'=HYPERLINK(1)", Cmshop_Csv::cell( 'name', '=HYPERLINK(1)' ) );
		$this->assertSame( "\t490154203237518", Cmshop_Csv::cell( 'imei1', '490154203237518' ) );
		$this->assertSame( "\t+8801712345678", Cmshop_Csv::cell( 'phone', '+8801712345678' ) );
		$this->assertSame( '-500.00', Cmshop_Csv::cell( 'profit', '-500.00' ) );
		$this->assertSame( "'-cmd", Cmshop_Csv::cell( 'phone', '-cmd' ) );

		$columns = Cmshop_Csv::columns( 'customers' );
		$row     = Cmshop_Csv::row( array( 'id' => 3, 'name' => '@x', 'phone' => '0171' ), $columns );
		$this->assertSame( array( '3', "'@x", "\t0171", '', '', '' ), $row );
		$this->assertSame( array(), Cmshop_Csv::columns( 'nope' ) );
	}

	public function test_item_helpers(): void {
		$this->assertSame( 'sold', Cmshop_Item::status_for_quantity( 'reserved', 0 ) );
		$this->assertSame( 'reserved', Cmshop_Item::status_for_quantity( 'reserved', 1 ) );
		$this->assertSame( 'in_stock', Cmshop_Item::status_for_quantity( 'sold', 3 ) );
		$this->assertSame( 'Cable', Cmshop_Item::display_name( array( 'item_type' => 'accessory', 'name' => 'Cable' ) ) );
		$this->assertSame( 'Good', Cmshop_Item::label( Cmshop_Item::grades(), 'good' ) );
		$this->assertSame( 'x', Cmshop_Item::label( Cmshop_Item::grades(), 'x' ) );
	}

	public function test_repair_status(): void {
		$this->assertTrue( Cmshop_Repair_Status::is_valid( 'ready' ) );
		$this->assertNotContains( 'collected', Cmshop_Repair_Status::open() );
		$this->assertSame( 'Ready to collect', Cmshop_Repair_Status::label( 'ready' ) );
		$this->assertSame( '0.00', Cmshop_Repair_Status::balance_due( array( 'estimate' => '100.00', 'final_cost' => '200.00', 'deposit' => '500.00' ) ) );
		$this->assertSame( '150.00', Cmshop_Repair_Status::balance_due( array( 'estimate' => '100.00', 'final_cost' => '200.00', 'deposit' => '50.00' ) ) );
	}

	public function test_add_another_copies_model_and_prices_but_not_the_phone_itself(): void {
		$copy = Cmshop_Item::copy_fields(
			array(
				'id'             => 9,
				'item_type'      => 'phone',
				'brand'          => 'Samsung',
				'model'          => 'Galaxy A15',
				'storage'        => '128GB',
				'color'          => 'Black',
				'grade'          => 'new',
				'cost_price'     => '15000.00',
				'sale_price'     => '18500.00',
				'supplier'       => 'Dhaka Mobile Traders',
				'imei1'          => '490154203237518',
				'imei2'          => '356938035643809',
				'serial'         => 'R58X',
				'battery_health' => 100,
				'status'         => 'sold',
				'notes'          => 'Box damaged',
			)
		);

		$this->assertSame( 'Galaxy A15', $copy['model'] );
		$this->assertSame( '18500.00', $copy['sale_price'] );
		$this->assertSame( 'Black', $copy['color'] );
		$this->assertArrayNotHasKey( 'imei1', $copy );
		$this->assertArrayNotHasKey( 'serial', $copy );
		$this->assertArrayNotHasKey( 'status', $copy );
		$this->assertArrayNotHasKey( 'notes', $copy );
		$this->assertArrayNotHasKey( 'id', $copy );
	}

	public function test_gallery_ids_are_cleaned(): void {
		$this->assertSame( array( 12, 7, 30 ), Cmshop_Item::gallery_ids( '12, 7,abc,,0,-4,7,30' ) );
		$this->assertSame( array(), Cmshop_Item::gallery_ids( '' ) );
		$this->assertCount( 12, Cmshop_Item::gallery_ids( implode( ',', range( 1, 20 ) ) ) );
		$this->assertSame( '12,7,30', Cmshop_Item::gallery_csv( array( 12, 7, 30 ) ) );
	}

	public function test_add_another_copies_photos(): void {
		$copy = Cmshop_Item::copy_fields( array( 'model' => 'A15', 'image_id' => 44, 'gallery_ids' => '45,46' ) );

		$this->assertSame( 44, $copy['image_id'] );
		$this->assertSame( '45,46', $copy['gallery_ids'] );
	}
}
