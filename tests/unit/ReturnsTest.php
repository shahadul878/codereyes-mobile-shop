<?php
/**
 * Tests for returning items from a sale.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ReturnsTest extends TestCase {

	/**
	 * Sale lines: a phone and 3 chargers.
	 *
	 * @return array[]
	 */
	private function lines(): array {
		return array(
			array(
				'id'           => 11,
				'quantity'     => 1,
				'returned_qty' => 0,
				'line_total'   => '18000.00',
				'unit_cost'    => '15000.00',
			),
			array(
				'id'           => 12,
				'quantity'     => 3,
				'returned_qty' => 1,
				'line_total'   => '2100.00',
				'unit_cost'    => '300.00',
			),
		);
	}

	public function test_refund_and_cost_of_returned_items(): void {
		$result = Cmshop_Returns::price( $this->lines(), array( 12 => 2 ), 2010000, 0 );

		$this->assertSame( 140000, $result['refund'] );
		$this->assertSame( 60000, $result['cost'] );
		$this->assertSame( array( 12 => 2 ), $result['quantities'] );
	}

	public function test_discount_is_shared_in_proportion(): void {
		$result = Cmshop_Returns::price( $this->lines(), array( 11 => 1 ), 2010000, 201000 );

		$this->assertSame( 1620000, $result['refund'] );
	}

	public function test_cannot_return_more_than_left(): void {
		$this->expectException( InvalidArgumentException::class );

		Cmshop_Returns::price( $this->lines(), array( 12 => 3 ), 2010000, 0 );
	}

	public function test_unknown_line_or_nothing_chosen_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );

		Cmshop_Returns::price( $this->lines(), array( 99 => 1 ), 2010000, 0 );
	}

	public function test_zero_quantities_are_ignored_but_one_is_needed(): void {
		$this->expectException( InvalidArgumentException::class );

		Cmshop_Returns::price( $this->lines(), array( 11 => 0 ), 2010000, 0 );
	}

	public function test_fully_returned_when_nothing_is_left(): void {
		$this->assertTrue( Cmshop_Returns::all_returned( $this->lines(), array( 11 => 1, 12 => 2 ) ) );
		$this->assertFalse( Cmshop_Returns::all_returned( $this->lines(), array( 11 => 1 ) ) );
	}
}
