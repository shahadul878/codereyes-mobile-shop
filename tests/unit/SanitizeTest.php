<?php
/**
 * Deep sanitising of submitted data.
 *
 * @package CodereyesMobileShopManager
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SanitizeTest extends TestCase {

	public function test_strings_are_sanitised_and_line_breaks_kept(): void {
		$clean = Cmshop_Sanitize::deep( array( 'name' => ' <b>Rahim</b> ', 'lines' => "356938035643809\n490154203237518" ) );

		$this->assertSame( 'Rahim', $clean['name'] );
		$this->assertSame( "356938035643809\n490154203237518", $clean['lines'] );
	}

	public function test_nested_arrays_are_sanitised(): void {
		$clean = Cmshop_Sanitize::deep( array( 'lines' => array( array( 'model' => '<script>x</script>A55' ) ) ) );

		$this->assertSame( 'A55', $clean['lines'][0]['model'] );
	}

	public function test_numbers_booleans_and_null_keep_their_type(): void {
		$clean = Cmshop_Sanitize::deep( array( 'qty' => 2, 'price' => 45999.5, 'held' => true, 'note' => null ) );

		$this->assertSame( array( 'qty' => 2, 'price' => 45999.5, 'held' => true, 'note' => null ), $clean );
	}

	public function test_unsafe_keys_are_dropped(): void {
		$clean = Cmshop_Sanitize::deep( array( 'ok_key' => 'a', 'itemId' => 'b', '<bad key>' => 'c', 3 => 'd' ) );

		$this->assertSame( array( 'ok_key' => 'a', 'itemId' => 'b', 3 => 'd' ), $clean );
	}

	public function test_too_deep_data_is_cut_off(): void {
		$deep = 'end';
		for ( $i = 0; $i < 12; $i++ ) {
			$deep = array( 'k' => $deep );
		}

		$clean = Cmshop_Sanitize::deep( $deep );

		$depth = 0;
		while ( is_array( $clean ) && array() !== $clean ) {
			$clean = $clean['k'];
			$depth++;
		}

		$this->assertSame( Cmshop_Sanitize::MAX_DEPTH, $depth );
	}

	public function test_a_scalar_comes_back_sanitised(): void {
		$this->assertSame( 'hi', Cmshop_Sanitize::deep( '<i>hi</i>' ) );
	}
}
