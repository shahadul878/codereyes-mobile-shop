<?php
/**
 * Tests for installment plans and collecting dues.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InstallmentsTest extends TestCase {

	public function test_plan_splits_the_due_evenly_with_the_rest_on_the_last(): void {
		$plan = Cmshop_Installments::plan( 1000000, 3, '2026-12-12', 'month' );

		$this->assertSame( array( 1, 2, 3 ), array_column( $plan, 'seq' ) );
		$this->assertSame( array( '2026-12-12', '2027-01-12', '2027-02-12' ), array_column( $plan, 'due_date' ) );
		$this->assertSame( array( 333333, 333333, 333334 ), array_column( $plan, 'amount' ) );
	}

	public function test_weekly_plan(): void {
		$this->assertSame( array( '2026-11-19', '2026-11-26' ), array_column( Cmshop_Installments::plan( 200000, 2, '2026-11-19', 'week' ), 'due_date' ) );
	}

	public function test_month_end_dates_do_not_skip_a_month(): void {
		$this->assertSame( array( '2027-01-31', '2027-02-28', '2027-03-31' ), array_column( Cmshop_Installments::plan( 300000, 3, '2027-01-31', 'month' ), 'due_date' ) );
	}

	public function test_plan_rejects_bad_input(): void {
		$this->expectException( InvalidArgumentException::class );

		Cmshop_Installments::plan( 1000, 13, '2026-12-12', 'month' );
	}

	public function test_plan_rejects_a_past_or_invalid_first_date(): void {
		$this->expectException( InvalidArgumentException::class );

		Cmshop_Installments::plan( 1000, 2, '12/12/2026', 'month' );
	}

	public function test_status_applies_payments_in_order(): void {
		$plan   = Cmshop_Installments::plan( 900000, 3, '2026-10-01', 'month' );
		$status = Cmshop_Installments::status( $plan, 450000, '2026-11-12' );

		$this->assertSame( array( 'paid', 'partial', 'upcoming' ), array_column( $status, 'state' ) );
		$this->assertSame( array( 0, 150000, 300000 ), array_column( $status, 'remaining' ) );
		$this->assertTrue( $status[1]['overdue'] );
		$this->assertFalse( $status[2]['overdue'] );
	}

	public function test_next_and_overdue_summary(): void {
		$plan    = Cmshop_Installments::plan( 900000, 3, '2026-10-01', 'month' );
		$summary = Cmshop_Installments::summary( Cmshop_Installments::status( $plan, 0, '2026-11-12' ) );

		$this->assertSame( 600000, $summary['overdue'] );
		$this->assertSame( '2026-10-01', $summary['next_date'] );
		$this->assertSame( 2, $summary['overdue_count'] );
	}

	public function test_collect_pays_oldest_sales_first(): void {
		$parts = Cmshop_Installments::allocate(
			700000,
			array(
				array(
					'id'  => 4,
					'due' => 500000,
				),
				array(
					'id'  => 9,
					'due' => 400000,
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'sale_id' => 4,
					'amount'  => 500000,
				),
				array(
					'sale_id' => 9,
					'amount'  => 200000,
				),
			),
			$parts
		);
	}

	public function test_collect_more_than_owed_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );

		Cmshop_Installments::allocate(
			500001,
			array(
				array(
					'id'  => 4,
					'due' => 500000,
				),
			)
		);
	}
}
