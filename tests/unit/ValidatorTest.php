<?php
/**
 * Tests for Cmshop_Validator and Cmshop_Input.
 *
 * @package CodereyesMobileShop
 */

use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase {

	private const IMEI = '490154203237518';

	public function test_valid_phone_is_quantity_one_and_named(): void {
		$result = Cmshop_Validator::item(
			array(
				'item_type'      => 'phone',
				'brand'          => 'Samsung',
				'model'          => 'Galaxy A15',
				'storage'        => '128GB',
				'imei1'          => '49-0154 2032 37518',
				'battery_health' => '৯৫',
				'cost_price'     => '৳ ১৫,০০০',
				'sale_price'     => '18500',
				'quantity'       => '7',
			)
		);

		$this->assertTrue( $result->is_valid(), print_r( $result->errors(), true ) );
		$data = $result->data();
		$this->assertSame( self::IMEI, $data['imei1'] );
		$this->assertSame( 1, $data['quantity'] );
		$this->assertSame( 95, $data['battery_health'] );
		$this->assertSame( '15000.00', $data['cost_price'] );
		$this->assertSame( 'Samsung Galaxy A15 128GB', $data['name'] );
		$this->assertSame( 'in_stock', $data['status'] );
	}

	public function test_phone_errors(): void {
		$errors = Cmshop_Validator::item(
			array(
				'item_type'      => 'phone',
				'imei1'          => '490154203237519',
				'imei2'          => '490154203237519',
				'grade'          => 'mint',
				'battery_health' => '120',
				'cost_price'     => '1,5',
			)
		)->errors();

		$this->assertArrayHasKey( 'brand', $errors );
		$this->assertArrayHasKey( 'model', $errors );
		$this->assertStringContainsString( 'not a valid IMEI', $errors['imei1'] );
		$this->assertArrayHasKey( 'imei2', $errors );
		$this->assertArrayHasKey( 'grade', $errors );
		$this->assertArrayHasKey( 'battery_health', $errors );
		$this->assertArrayHasKey( 'cost_price', $errors );
	}

	public function test_same_imei_twice_is_rejected(): void {
		$errors = Cmshop_Validator::item(
			array( 'item_type' => 'phone', 'brand' => 'A', 'model' => 'B', 'imei1' => self::IMEI, 'imei2' => self::IMEI )
		)->errors();

		$this->assertSame( array( 'imei2' ), array_keys( $errors ) );
	}

	public function test_accessory_with_zero_quantity_is_sold_out(): void {
		$result = Cmshop_Validator::item(
			array( 'item_type' => 'accessory', 'name' => 'Case', 'quantity' => '0', 'imei1' => 'ignored' )
		);

		$this->assertTrue( $result->is_valid() );
		$this->assertSame( 'sold', $result->data()['status'] );
		$this->assertSame( '', $result->data()['imei1'] );
		$this->assertSame( 'Case', $result->data()['name'] );
	}

	public function test_accessory_needs_name_and_sane_quantity(): void {
		$errors = Cmshop_Validator::item( array( 'item_type' => 'accessory', 'quantity' => '-1' ) )->errors();

		$this->assertArrayHasKey( 'name', $errors );
		$this->assertArrayHasKey( 'quantity', $errors );
	}

	public function test_customer(): void {
		$ok = Cmshop_Validator::customer( array( 'name' => 'Rahim', 'phone' => '+880 1712-345678' ) );
		$this->assertTrue( $ok->is_valid() );
		$this->assertSame( '+8801712345678', $ok->data()['phone'] );

		$bad = Cmshop_Validator::customer( array( 'phone' => '123', 'email' => 'nope' ) )->errors();
		$this->assertSame( array( 'name', 'phone', 'email' ), array_keys( $bad ) );
	}

	public function test_repair(): void {
		$ok = Cmshop_Validator::repair(
			array(
				'customer_name'  => 'Karim',
				'customer_phone' => '01712345678',
				'device'         => 'iPhone 11',
				'fault'          => 'Broken screen',
				'estimate'       => '4500',
				'deposit'        => '1000',
				'due_date'       => '2026-11-20',
			)
		);

		$this->assertTrue( $ok->is_valid(), print_r( $ok->errors(), true ) );
		$this->assertSame( 'received', $ok->data()['status'] );
		$this->assertNull( $ok->data()['final_cost'] );
		$this->assertSame( '3500.00', Cmshop_Repair_Status::balance_due( $ok->data() ) );

		$bad = Cmshop_Validator::repair( array( 'due_date' => '2026-02-30', 'status' => 'lost', 'imei' => '123' ) )->errors();
		foreach ( array( 'customer_name', 'customer_phone', 'device', 'fault', 'due_date', 'status', 'imei' ) as $field ) {
			$this->assertArrayHasKey( $field, $bad );
		}
	}

	public function test_text_length_limit(): void {
		$errors = Cmshop_Validator::customer( array( 'name' => str_repeat( 'a', 151 ), 'phone' => '01712345678' ) )->errors();

		$this->assertStringContainsString( '150', $errors['name'] );
	}

	public function test_with_error_keeps_first_error(): void {
		$result = ( new Cmshop_Validation_Result( array(), array( 'imei1' => 'first' ) ) )
			->with_error( 'imei1', 'second' )
			->with_error( 'other', 'x' );

		$this->assertSame( 'first', $result->errors()['imei1'] );
		$this->assertFalse( $result->is_valid() );
	}
}
