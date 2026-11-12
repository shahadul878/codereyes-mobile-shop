<?php
/**
 * Tests for turning block attributes into shortcode attributes.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BlockAttsTest extends TestCase {

	public function test_stock_block_maps_every_setting(): void {
		$this->assertSame(
			array(
				'type'      => 'accessory',
				'brand'     => 'Anker',
				'condition' => 'new',
				'per_page'  => '8',
				'filters'   => 'no',
				'columns'   => '3',
				'accent'    => '#7c3aed',
				'radius'    => '6',
			),
			Cmshop_Block_Atts::map(
				'stock',
				array(
					'type'         => 'accessory',
					'brand'        => 'Anker',
					'condition'    => 'new',
					'perPage'      => 8,
					'filters'      => false,
					'columns'      => 3,
					'accentColor'  => '#7c3aed',
					'cornerRadius' => 6,
				)
			)
		);
	}

	public function test_defaults_leave_style_and_columns_out(): void {
		$atts = Cmshop_Block_Atts::map(
			'stock',
			array(
				'type'        => 'phone',
				'columns'     => 0,
				'accentColor' => '',
			)
		);

		$this->assertArrayNotHasKey( 'columns', $atts );
		$this->assertArrayNotHasKey( 'accent', $atts );
		$this->assertArrayNotHasKey( 'radius', $atts );
		$this->assertSame( 'yes', $atts['filters'] );
	}

	public function test_form_blocks_only_pass_style(): void {
		$this->assertSame(
			array( 'radius' => '0' ),
			Cmshop_Block_Atts::map(
				'warranty',
				array(
					'cornerRadius' => 0,
					'type'         => 'accessory',
				)
			)
		);
	}
}
