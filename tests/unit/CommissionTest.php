<?php
/**
 * Staff commission.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CommissionTest extends TestCase {

	public function test_rate_is_read_as_a_percentage_in_basis_points(): void {
		$this->assertSame( 150, Cmshop_Commission::rate( '1.5' ) );
		$this->assertSame( 1000, Cmshop_Commission::rate( '১০' ) );
		$this->assertSame( 250, Cmshop_Commission::rate( ' 2.5 % ' ) );
		$this->assertSame( 0, Cmshop_Commission::rate( '' ) );
	}

	public function test_bad_or_out_of_range_rates_become_zero(): void {
		$this->assertSame( 0, Cmshop_Commission::rate( 'abc' ) );
		$this->assertSame( 0, Cmshop_Commission::rate( '-5' ) );
		$this->assertSame( 0, Cmshop_Commission::rate( '101' ) );
		$this->assertSame( 0, Cmshop_Commission::rate( '1.234' ) );
	}

	public function test_rate_shown_back_as_text(): void {
		$this->assertSame( '1.5', Cmshop_Commission::rate_text( 150 ) );
		$this->assertSame( '10', Cmshop_Commission::rate_text( 1000 ) );
		$this->assertSame( '0', Cmshop_Commission::rate_text( 0 ) );
	}

	public function test_commission_on_sales_or_on_profit_rounded_to_the_cent(): void {
		// 1.5% of ৳ 18,500.55 = ৳ 277.508 → 277.51.
		$this->assertSame( 27751, Cmshop_Commission::amount( 1850055, 300000, 150, Cmshop_Commission::ON_SALES ) );
		// 10% of ৳ 3,000 profit.
		$this->assertSame( 30000, Cmshop_Commission::amount( 1850055, 300000, 1000, Cmshop_Commission::ON_PROFIT ) );
	}

	public function test_no_commission_on_a_loss(): void {
		$this->assertSame( 0, Cmshop_Commission::amount( 100000, -5000, 1000, Cmshop_Commission::ON_PROFIT ) );
		$this->assertSame( 0, Cmshop_Commission::amount( -100000, 0, 1000, Cmshop_Commission::ON_SALES ) );
	}

	public function test_website_orders_earn_no_commission(): void {
		$report = Cmshop_Commission::report( array( array( 'user_id' => '0', 'sales' => '4', 'total' => '20000.00', 'profit' => '2000.00' ) ), 500, Cmshop_Commission::ON_SALES );

		$this->assertSame( 0, $report['rows'][0]['commission'] );
		$this->assertSame( 0, $report['totals']['commission'] );
	}

	public function test_report_rows_with_totals_best_seller_first(): void {
		$report = Cmshop_Commission::report(
			array(
				array( 'user_id' => '2', 'sales' => '3', 'total' => '10000.00', 'profit' => '1500.00' ),
				array( 'user_id' => '5', 'sales' => '7', 'total' => '45000.00', 'profit' => '6000.00' ),
			),
			200,
			Cmshop_Commission::ON_SALES
		);

		$this->assertSame( array( 5, 2 ), array_column( $report['rows'], 'user_id' ) );
		$this->assertSame( 90000, $report['rows'][0]['commission'] );
		$this->assertSame( 4500000, $report['rows'][0]['total'] );
		$this->assertSame( array( 'sales' => 10, 'total' => 5500000, 'profit' => 750000, 'commission' => 110000 ), $report['totals'] );
	}
}
