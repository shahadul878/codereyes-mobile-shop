<?php
/**
 * Tests for dashboard figures.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DashboardMathTest extends TestCase {

	public function test_fills_missing_days_with_zero(): void {
		$days = Cmshop_Dashboard_Math::fill_days(
			array(
				array(
					'day'    => '2026-11-10',
					'total'  => '1500.00',
					'profit' => '200.00',
					'sales'  => '2',
				),
			),
			'2026-11-09',
			3
		);

		$this->assertSame( array( '2026-11-09', '2026-11-10', '2026-11-11' ), array_column( $days, 'day' ) );
		$this->assertSame( array( 0, 150000, 0 ), array_column( $days, 'total' ) );
		$this->assertSame( array( 0, 20000, 0 ), array_column( $days, 'profit' ) );
		$this->assertSame( array( 0, 2, 0 ), array_column( $days, 'sales' ) );
	}

	public function test_bar_heights_are_percent_of_the_best_day(): void {
		$bars = Cmshop_Dashboard_Math::bar_heights(
			array(
				array(
					'total'  => 50000,
					'profit' => 10000,
				),
				array(
					'total'  => 200000,
					'profit' => 30000,
				),
				array(
					'total'  => 0,
					'profit' => 0,
				),
			)
		);

		$this->assertSame( array( 25, 100, 0 ), array_column( $bars, 'total_pct' ) );
		$this->assertSame( array( 5, 15, 0 ), array_column( $bars, 'profit_pct' ) );
	}

	public function test_bar_heights_with_no_sales_are_zero(): void {
		$this->assertSame( array( 0 ), array_column( Cmshop_Dashboard_Math::bar_heights( array( array( 'total' => 0, 'profit' => 0 ) ) ), 'total_pct' ) );
	}

	public function test_change_against_yesterday(): void {
		$this->assertSame( 12, Cmshop_Dashboard_Math::change( 112000, 100000 ) );
		$this->assertSame( -50, Cmshop_Dashboard_Math::change( 50000, 100000 ) );
		$this->assertNull( Cmshop_Dashboard_Math::change( 50000, 0 ) );
	}

	public function test_margin(): void {
		$this->assertSame( 14, Cmshop_Dashboard_Math::margin( 1400, 10000 ) );
		$this->assertSame( 0, Cmshop_Dashboard_Math::margin( 100, 0 ) );
	}

	public function test_donut_segments_share_of_positive_totals(): void {
		$segments = Cmshop_Dashboard_Math::donut(
			array(
				'cash'           => '4800.00',
				'mobile_banking' => '3600.00',
				'card'           => '1600.00',
				'bank_transfer'  => '-50.00',
			)
		);

		$this->assertSame( array( 'cash', 'mobile_banking', 'card' ), array_column( $segments, 'key' ) );
		$this->assertSame( array( 48, 36, 16 ), array_column( $segments, 'percent' ) );
		$this->assertSame( array( 0, 48, 84 ), array_column( $segments, 'start' ) );
	}

	public function test_donut_percents_add_up_to_100(): void {
		$segments = Cmshop_Dashboard_Math::donut(
			array(
				'cash' => '1',
				'card' => '1',
				'bank' => '1',
			)
		);

		$this->assertSame( 100, array_sum( array_column( $segments, 'percent' ) ) );
	}
}
