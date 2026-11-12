<?php
/**
 * Tests for choosing the invoice print format.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InvoiceFormatTest extends TestCase {

	public function test_offers_thermal_and_a4(): void {
		$this->assertSame( array( 'thermal', 'a4' ), array_keys( Cmshop_Invoice_Format::all() ) );
	}

	public function test_uses_requested_format_when_valid(): void {
		$this->assertSame( 'a4', Cmshop_Invoice_Format::resolve( 'a4', 'thermal' ) );
		$this->assertSame( 'thermal', Cmshop_Invoice_Format::resolve( 'thermal', 'a4' ) );
	}

	public function test_falls_back_to_shop_default_when_nothing_requested(): void {
		$this->assertSame( 'a4', Cmshop_Invoice_Format::resolve( '', 'a4' ) );
	}

	public function test_ignores_unknown_format(): void {
		$this->assertSame( 'a4', Cmshop_Invoice_Format::resolve( 'letter', 'a4' ) );
	}

	public function test_falls_back_to_thermal_when_default_is_also_invalid(): void {
		$this->assertSame( 'thermal', Cmshop_Invoice_Format::resolve( 'x', 'y' ) );
	}

	public function test_thermal_widths_are_80_and_58_mm(): void {
		$this->assertSame( array( '80', '58' ), array_map( 'strval', array_keys( Cmshop_Invoice_Format::thermal_widths() ) ) );
	}

	public function test_unknown_thermal_width_becomes_80(): void {
		$this->assertSame( '58', Cmshop_Invoice_Format::thermal_width( '58' ) );
		$this->assertSame( '80', Cmshop_Invoice_Format::thermal_width( '76' ) );
		$this->assertSame( '80', Cmshop_Invoice_Format::thermal_width( '' ) );
	}
}
