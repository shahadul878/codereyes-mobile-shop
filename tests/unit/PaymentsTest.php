<?php
/**
 * Tests for splitting a sale's payment across methods.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PaymentsTest extends TestCase {

	private const METHODS = array( 'cash', 'card', 'mobile_banking', 'bank_transfer', 'online' );

	public function test_cash_and_bkash_split(): void {
		$result = Cmshop_Payments::allocate(
			array(
				array(
					'method' => 'cash',
					'amount' => '10000',
				),
				array(
					'method'    => 'mobile_banking',
					'amount'    => '৮,৫০০',
					'reference' => ' TRX9K2 ',
				),
			),
			1850000,
			self::METHODS
		);

		$this->assertSame( 1850000, $result['paid'] );
		$this->assertSame( 0, $result['change'] );
		$this->assertSame( 0, $result['due'] );
		$this->assertSame( array( 'cash', 'mobile_banking' ), array_column( $result['entries'], 'method' ) );
		$this->assertSame( array( '10000.00', '8500.00' ), array_column( $result['entries'], 'amount' ) );
		$this->assertSame( 'TRX9K2', $result['entries'][1]['reference'] );
	}

	public function test_change_comes_out_of_cash(): void {
		$result = Cmshop_Payments::allocate(
			array(
				array(
					'method' => 'card',
					'amount' => '5000',
				),
				array(
					'method' => 'cash',
					'amount' => '20000',
				),
			),
			1850000,
			self::METHODS
		);

		$this->assertSame( 650000, $result['change'] );
		$this->assertSame( 1850000, $result['paid'] );
		$this->assertSame( '13500.00', $result['entries'][1]['amount'] );
	}

	public function test_part_payment_leaves_a_balance(): void {
		$result = Cmshop_Payments::allocate( array( array( 'method' => 'cash', 'amount' => '5000' ) ), 1850000, self::METHODS );

		$this->assertSame( 1350000, $result['due'] );
		$this->assertSame( 500000, $result['paid'] );
	}

	public function test_empty_rows_and_zero_amounts_are_ignored(): void {
		$result = Cmshop_Payments::allocate(
			array(
				array(
					'method' => 'cash',
					'amount' => '',
				),
				array(
					'method' => 'card',
					'amount' => '0',
				),
			),
			100000,
			self::METHODS
		);

		$this->assertSame( array(), $result['entries'] );
		$this->assertSame( 100000, $result['due'] );
	}

	public function test_same_method_rows_are_merged(): void {
		$result = Cmshop_Payments::allocate(
			array(
				array(
					'method' => 'cash',
					'amount' => '300',
				),
				array(
					'method' => 'cash',
					'amount' => '200',
				),
			),
			50000,
			self::METHODS
		);

		$this->assertCount( 1, $result['entries'] );
		$this->assertSame( '500.00', $result['entries'][0]['amount'] );
	}

	public function test_non_cash_cannot_exceed_the_total(): void {
		$this->expectException( InvalidArgumentException::class );

		Cmshop_Payments::allocate( array( array( 'method' => 'card', 'amount' => '20000' ) ), 1850000, self::METHODS );
	}

	public function test_unknown_method_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );

		Cmshop_Payments::allocate( array( array( 'method' => 'bitcoin', 'amount' => '100' ) ), 10000, self::METHODS );
	}

	public function test_bad_amount_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );

		Cmshop_Payments::allocate( array( array( 'method' => 'cash', 'amount' => '-50' ) ), 10000, self::METHODS );
	}

	public function test_too_many_rows_are_refused(): void {
		$this->expectException( InvalidArgumentException::class );

		Cmshop_Payments::allocate( array_fill( 0, 6, array( 'method' => 'cash', 'amount' => '1' ) ), 10000, self::METHODS );
	}

	public function test_summary_method_is_the_single_method_or_split(): void {
		$this->assertSame( 'card', Cmshop_Payments::summary_method( array( array( 'method' => 'card' ) ), 'cash' ) );
		$this->assertSame( 'split', Cmshop_Payments::summary_method( array( array( 'method' => 'card' ), array( 'method' => 'cash' ) ), 'cash' ) );
		$this->assertSame( 'cash', Cmshop_Payments::summary_method( array(), 'cash' ) );
	}
}
