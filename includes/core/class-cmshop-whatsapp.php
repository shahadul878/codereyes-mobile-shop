<?php
/**
 * WhatsApp click-to-chat links.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Builds wa.me links with a ready-written message. Staff tap the link, check
 * the text in WhatsApp and press send, so no API account, fees or customer
 * data leave the shop until they choose to send.
 */
final class Cmshop_Whatsapp {

	public const DEFAULT_COUNTRY = '880';

	private const BASE        = 'https://wa.me/';
	private const MIN_DIGITS  = 8;
	private const MAX_DIGITS  = 15;
	private const MAX_COUNTRY = 4;
	private const PLACEHOLDER = '/\{([a-z_]+)\}/';

	/**
	 * Digits-only country calling code, or the default.
	 *
	 * @param string $raw Typed code, e.g. "+880".
	 * @return string
	 */
	public static function country( string $raw ): string {
		$digits = ltrim( Cmshop_Digits::only( Cmshop_Digits::to_ascii( $raw ) ), '0' );

		return '' === $digits || strlen( $digits ) > self::MAX_COUNTRY ? self::DEFAULT_COUNTRY : $digits;
	}

	/**
	 * Number in the international form wa.me expects, or null when unusable.
	 *
	 * "01712-345678" and "+880 1712 345678" both become "8801712345678".
	 *
	 * @param string $raw     Phone as typed or stored.
	 * @param string $country Country calling code for local numbers.
	 * @return string|null
	 */
	public static function phone( string $raw, string $country ): ?string {
		$ascii   = trim( Cmshop_Digits::to_ascii( $raw ) );
		$digits  = Cmshop_Digits::only( $ascii );
		$is_intl = 0 === strpos( $ascii, '+' ) || 0 === strpos( $digits, '00' );

		if ( 0 === strpos( $digits, '00' ) ) {
			$digits = substr( $digits, 2 );
		}

		if ( ! $is_intl && 0 !== strpos( $digits, $country ) ) {
			$digits = $country . ltrim( $digits, '0' );
		}

		$length = strlen( $digits );

		return $length >= self::MIN_DIGITS && $length <= self::MAX_DIGITS ? $digits : null;
	}

	/**
	 * Message from a template with {placeholders}.
	 *
	 * A line that only held placeholders which came out empty is dropped, so
	 * optional parts (balance due, next instalment) vanish cleanly. Unknown
	 * placeholders are left as typed so mistakes in a template are visible.
	 *
	 * @param string               $template Template.
	 * @param array<string,string> $vars     Values by placeholder name.
	 * @return string
	 */
	public static function fill( string $template, array $vars ): string {
		$lines = array();

		foreach ( preg_split( '/\R/', $template ) as $line ) {
			$filled = (string) preg_replace_callback(
				self::PLACEHOLDER,
				static function ( array $found ) use ( $vars ): string {
					return array_key_exists( $found[1], $vars ) ? (string) $vars[ $found[1] ] : $found[0];
				},
				$line
			);

			if ( '' === trim( $filled ) && '' !== trim( $line ) ) {
				continue;
			}

			$lines[] = $filled;
		}

		return trim( implode( "\n", $lines ) );
	}

	/**
	 * Click-to-chat link. Without a number, WhatsApp asks which chat to use.
	 *
	 * @param string|null $phone International number (digits).
	 * @param string      $text  Message.
	 * @return string
	 */
	public static function link( ?string $phone, string $text ): string {
		return self::BASE . ( null === $phone ? '' : $phone ) . '?text=' . rawurlencode( $text );
	}

	/**
	 * "• Item × 2 — ৳ 700" lines.
	 *
	 * @param array<int, array{0: string, 1: int, 2: string}> $rows Description, quantity, formatted amount.
	 * @return string
	 */
	public static function bullets( array $rows ): string {
		return implode(
			"\n",
			array_map(
				static function ( array $row ): string {
					return sprintf( '• %1$s × %2$d — %3$s', $row[0], (int) $row[1], $row[2] );
				},
				$rows
			)
		);
	}
}
