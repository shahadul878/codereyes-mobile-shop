<?php
/**
 * Tests for cash drawer counts and shift summaries.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ShiftMathTest extends TestCase {

	public function test_counts_notes_and_coins(): void {
		$this->assertSame( 2865000, Cmshop_Shift_Math::count( array( '1000' => '২০', '500' => '15', '200' => '3', '100' => '5', '50' => '1', 'bogus' => '9', '20' => '-3' ) ) );
		$this->assertSame( 0, Cmshop_Shift_Math::count( array() ) );
	}

	public function test_summary_by_method_and_kind(): void {
		$summary = Cmshop_Shift_Math::summary(
			array(
				array(
					'kind'   => 'sale',
					'method' => 'cash',
					'amount' => '18500.00',
				),
				array(
					'kind'   => 'sale',
					'method' => 'mobile_banking',
					'amount' => '9000.00',
				),
				array(
					'kind'   => 'due',
					'method' => 'cash',
					'amount' => '2000.00',
				),
				array(
					'kind'   => 'refund',
					'method' => 'cash',
					'amount' => '-900.00',
				),
				array(
					'kind'   => 'cash_out',
					'method' => 'cash',
					'amount' => '-300.00',
				),
				array(
					'kind'   => 'cash_in',
					'method' => 'cash',
					'amount' => '1000.00',
				),
				array(
					'kind'   => 'sale',
					'method' => 'trade_in',
					'amount' => '22000.00',
				),
			),
			500000
		);

		$this->assertSame( 2030000, $summary['cash_net'] );
		$this->assertSame( 2530000, $summary['expected'] );
		$this->assertSame( array( 'cash' => 2030000, 'mobile_banking' => 900000, 'trade_in' => 2200000 ), $summary['by_method'] );
		$this->assertSame( 90000, $summary['refunds'] );
		$this->assertSame( 30000, $summary['cash_out'] );
		$this->assertSame( 100000, $summary['cash_in'] );
		$this->assertSame( 200000, $summary['dues'] );
	}

	public function test_supplier_payments_leave_the_drawer_but_are_not_sales(): void {
		$summary = Cmshop_Shift_Math::summary(
			array(
				array(
					'kind'   => 'sale',
					'method' => 'cash',
					'amount' => '5000.00',
				),
				array(
					'kind'   => 'supplier',
					'method' => 'cash',
					'amount' => '-3000.00',
				),
			),
			100000
		);

		$this->assertSame( 500000, $summary['sales'] );
		$this->assertSame( 300000, $summary['suppliers'] );
		$this->assertSame( 300000, $summary['expected'] );
	}

	public function test_difference_short_and_over(): void {
		$this->assertSame( -5000, Cmshop_Shift_Math::difference( 995000, 1000000 ) );
		$this->assertSame( 2000, Cmshop_Shift_Math::difference( 1002000, 1000000 ) );
	}
}
