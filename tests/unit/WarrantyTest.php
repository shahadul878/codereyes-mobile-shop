<?php
/**
 * Tests for warranty status by sale date.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WarrantyTest extends TestCase {

	public function test_active_within_the_period(): void {
		$status = Cmshop_Warranty::status( '2026-01-10 14:30:00', 365, '2026-11-12' );

		$this->assertSame( 'active', $status['state'] );
		$this->assertSame( '2027-01-10', $status['ends'] );
		$this->assertSame( 59, $status['days_left'] );
	}

	public function test_last_day_is_still_covered(): void {
		$status = Cmshop_Warranty::status( '2026-11-05 09:00:00', 7, '2026-11-12' );

		$this->assertSame( 'active', $status['state'] );
		$this->assertSame( 0, $status['days_left'] );
	}

	public function test_expired_after_the_period(): void {
		$status = Cmshop_Warranty::status( '2026-01-10 14:30:00', 30, '2026-11-12' );

		$this->assertSame( 'expired', $status['state'] );
		$this->assertSame( '2026-02-09', $status['ends'] );
		$this->assertSame( 0, $status['days_left'] );
	}

	public function test_zero_days_means_no_warranty(): void {
		$this->assertSame( 'none', Cmshop_Warranty::status( '2026-11-01 10:00:00', 0, '2026-11-12' )['state'] );
	}

	public function test_days_depend_on_condition(): void {
		$this->assertSame( 365, Cmshop_Warranty::days_for( 'new', 365, 30 ) );
		$this->assertSame( 30, Cmshop_Warranty::days_for( 'good', 365, 30 ) );
		$this->assertSame( 30, Cmshop_Warranty::days_for( 'like_new', 365, 30 ) );
	}
}
