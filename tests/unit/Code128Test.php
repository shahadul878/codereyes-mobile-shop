<?php
/**
 * Code 128 barcode encoder.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Code128Test extends TestCase {

	public function test_every_pattern_is_eleven_modules_wide(): void {
		foreach ( Cmshop_Code128::PATTERNS as $value => $pattern ) {
			$expected = 106 === $value ? 13 : 11;
			$this->assertSame( $expected, array_sum( array_map( 'intval', str_split( $pattern ) ) ), "Pattern $value" );
		}
		$this->assertCount( 107, Cmshop_Code128::PATTERNS );
	}

	public function test_text_uses_code_b_with_checksum(): void {
		// Start B 104 + P48*1 + J42*2 + J42*3 + 1(17)*4 + 2(18)*5 + 3(19)*6 + C35*7 = 879; 879 % 103 = 55.
		$this->assertSame( array( 104, 48, 42, 42, 17, 18, 19, 35, 55, 106 ), Cmshop_Code128::symbols( 'PJJ123C' ) );
	}

	public function test_even_digits_use_code_c_pairs(): void {
		// Start C 105 + 12*1 + 34*2 = 185; 185 % 103 = 82.
		$this->assertSame( array( 105, 12, 34, 82, 106 ), Cmshop_Code128::symbols( '1234' ) );
	}

	public function test_imei_uses_code_c_then_b_for_the_last_digit(): void {
		$symbols = Cmshop_Code128::symbols( '356938035643809' );

		$this->assertSame( 105, $symbols[0] );
		$this->assertSame( array( 35, 69, 38, 3, 56, 43, 80 ), array_slice( $symbols, 1, 7 ) );
		$this->assertSame( array( 100, 25 ), array_slice( $symbols, 8, 2 ) );
		$this->assertCount( 12, $symbols );
	}

	public function test_modules_have_quiet_zones_and_end_on_a_bar(): void {
		$modules = Cmshop_Code128::modules( '1234' );

		$this->assertSame( str_repeat( '0', 10 ), substr( $modules, 0, 10 ) );
		$this->assertSame( str_repeat( '0', 10 ), substr( $modules, -10 ) );
		// 4 symbols * 11 + stop 13 + quiet zones 20.
		$this->assertSame( 77, strlen( $modules ) );
		$this->assertSame( '11010011100', substr( $modules, 10, 11 ) );
	}

	public function test_svg_draws_one_rect_per_bar(): void {
		$svg = Cmshop_Code128::svg( '1234' );

		$this->assertStringStartsWith( '<svg', $svg );
		$this->assertStringContainsString( 'viewBox="0 0 77 ', $svg );
		$bars = preg_match_all( '/1+/', Cmshop_Code128::modules( '1234' ) );
		$this->assertSame( $bars, substr_count( $svg, '<rect' ) );
	}

	public function test_rejects_empty_long_or_non_ascii_values(): void {
		foreach ( array( '', str_repeat( 'A', 49 ), 'ফোন', "A\nB" ) as $bad ) {
			try {
				Cmshop_Code128::symbols( $bad );
				$this->fail( 'Accepted: ' . $bad );
			} catch ( InvalidArgumentException $e ) {
				$this->assertNotSame( '', $e->getMessage() );
			}
		}
	}
}
