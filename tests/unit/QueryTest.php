<?php
/**
 * Tests for Cmshop_Query.
 *
 * @package CodereyesMobileShop
 */

use PHPUnit\Framework\TestCase;

final class QueryTest extends TestCase {

	private function query(): Cmshop_Query {
		return new Cmshop_Query( array( 'name', 'imei1' ), array( 'status', 'item_type' ), array( 'name', 'created_at' ), 'created_at' );
	}

	public function test_empty_args_match_everything(): void {
		$this->assertSame( array( 'WHERE 1=1', array() ), $this->query()->where( array() ) );
	}

	public function test_search_filters_and_dates(): void {
		list( $sql, $params ) = $this->query()->where(
			array(
				'search'  => '50%_off',
				'filters' => array(
					'status'    => array( 'in_stock', 'reserved' ),
					'item_type' => 'phone',
					'evil; --'  => 'x',
					'grade'     => 'good',
				),
				'from'    => '2026-11-01',
				'to'      => '2026-11-30',
			)
		);

		$this->assertSame(
			'WHERE 1=1 AND ( name LIKE %s OR imei1 LIKE %s ) AND status IN ( %s, %s ) AND item_type IN ( %s ) AND created_at >= %s AND created_at < %s',
			$sql
		);
		$this->assertSame( array( '%50\%\_off%', '%50\%\_off%', 'in_stock', 'reserved', 'phone', '2026-11-01', '2026-12-01' ), $params );
	}

	public function test_invalid_dates_are_ignored(): void {
		list( $sql ) = $this->query()->where( array( 'from' => '2026-02-30', 'to' => 'yesterday' ) );

		$this->assertSame( 'WHERE 1=1', $sql );
	}

	public function test_order_by_whitelist(): void {
		$this->assertSame( 'ORDER BY name ASC, id ASC', $this->query()->order_by( array( 'orderby' => 'name', 'order' => 'asc' ) ) );
		$this->assertSame( 'ORDER BY created_at DESC, id DESC', $this->query()->order_by( array( 'orderby' => 'id; DROP', 'order' => 'x' ) ) );
	}

	public function test_limit_is_clamped(): void {
		$this->assertSame( array( 20, 0 ), Cmshop_Query::limit( array() ) );
		$this->assertSame( array( 200, 400 ), Cmshop_Query::limit( array( 'per_page' => 999, 'page' => 3 ) ) );
		$this->assertSame( array( 1, 0 ), Cmshop_Query::limit( array( 'per_page' => -5, 'page' => -1 ) ) );
	}
}
