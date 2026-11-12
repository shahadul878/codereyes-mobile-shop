<?php
/**
 * Tests for the style options shared by shortcodes, blocks and widgets.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StyleTest extends TestCase {

	public function test_accepts_hex_colours_only(): void {
		$this->assertSame( '#0d9488', Cmshop_Style::color( '#0D9488' ) );
		$this->assertSame( '#abc', Cmshop_Style::color( ' #abc ' ) );
		$this->assertSame( '', Cmshop_Style::color( 'red' ) );
		$this->assertSame( '', Cmshop_Style::color( '#12345' ) );
		$this->assertSame( '', Cmshop_Style::color( '#fff;background:url(x)' ) );
	}

	public function test_css_vars_from_valid_options(): void {
		$this->assertSame(
			'--cmshop-accent:#7c3aed;--cmshop-radius:20px;--cmshop-columns:4',
			Cmshop_Style::css_vars(
				array(
					'accent'  => '#7c3aed',
					'radius'  => '20',
					'columns' => '4',
				)
			)
		);
	}

	public function test_css_vars_clamp_and_skip(): void {
		$this->assertSame( '--cmshop-radius:32px;--cmshop-columns:1', Cmshop_Style::css_vars( array( 'accent' => 'blue', 'radius' => '99', 'columns' => '0' ) ) );
		$this->assertSame( '--cmshop-radius:0px', Cmshop_Style::css_vars( array( 'radius' => '0' ) ) );
		$this->assertSame( '', Cmshop_Style::css_vars( array( 'radius' => '', 'columns' => 'x' ) ) );
	}

	public function test_swatch_matches_colour_words(): void {
		$this->assertSame( '#111827', Cmshop_Style::swatch( 'Midnight Black' ) );
		$this->assertSame( '#98e2c6', Cmshop_Style::swatch( 'mint' ) );
		$this->assertSame( '#2563eb', Cmshop_Style::swatch( 'Ocean Blue' ) );
		$this->assertSame( '#9ca3af', Cmshop_Style::swatch( 'Space Grey' ) );
		$this->assertSame( '', Cmshop_Style::swatch( 'Aurora' ) );
	}
}
