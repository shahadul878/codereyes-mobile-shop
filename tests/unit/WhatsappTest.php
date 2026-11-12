<?php
/**
 * WhatsApp click-to-chat links.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WhatsappTest extends TestCase {

	public function test_local_numbers_get_the_country_code(): void {
		$this->assertSame( '8801712345678', Cmshop_Whatsapp::phone( '01712-345678', '880' ) );
		$this->assertSame( '8801712345678', Cmshop_Whatsapp::phone( '০১৭১২৩৪৫৬৭৮', '880' ) );
	}

	public function test_international_numbers_are_kept(): void {
		$this->assertSame( '8801712345678', Cmshop_Whatsapp::phone( '+880 1712 345678', '880' ) );
		$this->assertSame( '8801712345678', Cmshop_Whatsapp::phone( '008801712345678', '880' ) );
		$this->assertSame( '8801712345678', Cmshop_Whatsapp::phone( '8801712345678', '880' ) );
		$this->assertSame( '447700900123', Cmshop_Whatsapp::phone( '+44 7700 900123', '880' ) );
	}

	public function test_numbers_without_a_trunk_zero_get_the_country_code(): void {
		$this->assertSame( '8801712345678', Cmshop_Whatsapp::phone( '1712345678', '880' ) );
	}

	public function test_unusable_numbers_return_null(): void {
		$this->assertNull( Cmshop_Whatsapp::phone( '', '880' ) );
		$this->assertNull( Cmshop_Whatsapp::phone( '123', '880' ) );
		$this->assertNull( Cmshop_Whatsapp::phone( '+1234567890123456', '880' ) );
	}

	public function test_country_code_is_cleaned(): void {
		$this->assertSame( '880', Cmshop_Whatsapp::country( '+880' ) );
		$this->assertSame( '880', Cmshop_Whatsapp::country( 'abc' ) );
		$this->assertSame( '44', Cmshop_Whatsapp::country( '0044' ) );
	}

	public function test_fill_replaces_placeholders(): void {
		$this->assertSame(
			"Hi Karim,\nTotal: ৳ 500",
			Cmshop_Whatsapp::fill( "Hi {name},\nTotal: {total}", array( 'name' => 'Karim', 'total' => '৳ 500' ) )
		);
	}

	public function test_lines_left_empty_by_placeholders_are_dropped_but_blank_lines_stay(): void {
		$template = "Hi {name}\n\nStill to pay: {due}\n{due_line}\nThanks";

		$this->assertSame( "Hi Karim\n\nStill to pay: \nThanks", Cmshop_Whatsapp::fill( $template, array( 'name' => 'Karim', 'due' => '', 'due_line' => '' ) ) );
	}

	public function test_unknown_placeholders_stay_visible(): void {
		$this->assertSame( 'Hi {nmae}', Cmshop_Whatsapp::fill( 'Hi {nmae}', array( 'name' => 'Karim' ) ) );
	}

	public function test_link_encodes_text_for_the_chat(): void {
		$this->assertSame( 'https://wa.me/8801712345678?text=Hi%20Karim%0ATotal%3A%20%E0%A7%B3%20500%20%26%20more', Cmshop_Whatsapp::link( '8801712345678', "Hi Karim\nTotal: ৳ 500 & more" ) );
	}

	public function test_link_without_a_number_lets_staff_pick_the_chat(): void {
		$this->assertSame( 'https://wa.me/?text=Hi', Cmshop_Whatsapp::link( null, 'Hi' ) );
	}

	public function test_items_list_is_one_line_each(): void {
		$this->assertSame(
			"• Galaxy A15 128GB × 1 — ৳ 18,500\n• USB-C cable × 2 — ৳ 700",
			Cmshop_Whatsapp::bullets( array( array( 'Galaxy A15 128GB', 1, '৳ 18,500' ), array( 'USB-C cable', 2, '৳ 700' ) ) )
		);
	}
}
