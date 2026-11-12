<?php
/**
 * Tests for Cmshop_Cart.
 *
 * @package CodereyesMobileShop
 */

use PHPUnit\Framework\TestCase;

final class CartTest extends TestCase {

	private function items(): array {
		return array(
			1 => array(
				'id'         => 1,
				'item_type'  => 'phone',
				'brand'      => 'Samsung',
				'model'      => 'Galaxy A15',
				'storage'    => '128GB',
				'color'      => 'Black',
				'imei1'      => '490154203237518',
				'quantity'   => 1,
				'status'     => 'in_stock',
				'cost_price' => '15000.00',
				'sale_price' => '18500.00',
			),
			2 => array(
				'id'         => 2,
				'item_type'  => 'accessory',
				'name'       => '20W charger',
				'imei1'      => '',
				'quantity'   => 5,
				'status'     => 'in_stock',
				'cost_price' => '300.00',
				'sale_price' => '650.00',
			),
		);
	}

	public function test_prices_lines_from_stock_when_no_price_given(): void {
		$sale = Cmshop_Cart::price(
			array(
				array( 'item_id' => 1, 'quantity' => 1 ),
				array( 'item_id' => 2, 'quantity' => 2 ),
			),
			$this->items(),
			'',
			''
		);

		$this->assertSame( '19800.00', $sale['subtotal'] );
		$this->assertSame( '19800.00', $sale['total'] );
		$this->assertSame( '15600.00', $sale['cost_total'] );
		$this->assertSame( '4200.00', $sale['profit'] );
		$this->assertSame( '19800.00', $sale['amount_paid'] );
		$this->assertSame( 'Samsung Galaxy A15 128GB Black', $sale['lines'][0]['description'] );
		$this->assertSame( '1300.00', $sale['lines'][1]['line_total'] );
	}

	public function test_staff_price_override_and_discount_and_change(): void {
		$sale = Cmshop_Cart::price(
			array( array( 'item_id' => 1, 'quantity' => 1, 'unit_price' => '18,000' ) ),
			$this->items(),
			'500',
			'20000'
		);

		$this->assertSame( '17500.00', $sale['total'] );
		$this->assertSame( '2500.00', $sale['profit'] );
		$this->assertSame( '2500.00', $sale['change'] );
		$this->assertSame( '0.00', $sale['balance_due'] );
	}

	public function test_part_payment_leaves_balance_due(): void {
		$sale = Cmshop_Cart::price( array( array( 'item_id' => 1, 'quantity' => 1 ) ), $this->items(), '', '10000' );

		$this->assertSame( '8500.00', $sale['balance_due'] );
		$this->assertSame( '0.00', $sale['change'] );
	}

	/**
	 * @dataProvider invalid_sales
	 */
	public function test_rejects_invalid_sales( array $lines, string $discount, string $expected ): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( $expected );

		Cmshop_Cart::price( $lines, $this->items(), $discount, '' );
	}

	public function invalid_sales(): array {
		return array(
			'empty'            => array( array(), '', 'at least one item' ),
			'missing item'     => array( array( array( 'item_id' => 99, 'quantity' => 1 ) ), '', 'no longer exists' ),
			'too many'         => array( array( array( 'item_id' => 2, 'quantity' => 6 ) ), '', 'only 5 in stock' ),
			'phone twice'      => array( array( array( 'item_id' => 1, 'quantity' => 2 ) ), '', 'only 1 in stock' ),
			'zero quantity'    => array( array( array( 'item_id' => 2, 'quantity' => 0 ) ), '', 'only 5 in stock' ),
			'duplicate line'   => array( array( array( 'item_id' => 2, 'quantity' => 1 ), array( 'item_id' => 2, 'quantity' => 1 ) ), '', 'listed twice' ),
			'big discount'     => array( array( array( 'item_id' => 2, 'quantity' => 1 ) ), '651', 'larger than the sale' ),
			'bad discount'     => array( array( array( 'item_id' => 2, 'quantity' => 1 ) ), 'abc', 'discount is not' ),
			'negative price'   => array( array( array( 'item_id' => 2, 'quantity' => 1, 'unit_price' => '-5' ) ), '', 'price is not' ),
		);
	}

	public function test_sold_item_cannot_be_sold_again(): void {
		$items              = $this->items();
		$items[1]['status'] = 'sold';

		$this->expectExceptionMessage( 'only 0 in stock' );

		Cmshop_Cart::price( array( array( 'item_id' => 1, 'quantity' => 1 ) ), $items, '', '' );
	}
}
