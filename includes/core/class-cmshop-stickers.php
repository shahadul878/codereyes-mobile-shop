<?php
/**
 * Price sticker contents.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * What goes on a shelf sticker: name, variant, condition, price and a
 * barcode the POS can scan. Never carries the cost price.
 */
final class Cmshop_Stickers {

	public const MAX_LABELS = 300;

	private const CODE_PREFIX = 'CMS';
	private const CODE_DIGITS = 6;
	private const IMEI_LENGTH = 15;
	// Widest barcode that reliably scans on a 38 mm label from a 203 dpi printer
	// (a 10-character SKU; an IMEI is 154). Measured by decoding simulated prints.
	private const MAX_MODULES = 165;
	private const COPIES      = array( '1', '2', '3', 'stock' );

	/**
	 * Label sizes: key => [label, width mm, height mm].
	 *
	 * @return array<string, array{0: string, 1: float, 2: float}>
	 */
	public static function sizes(): array {
		return array(
			'38x25' => array( __( 'Label roll 38 × 25 mm', 'codereyes-mobile-shop' ), 38.0, 25.0 ),
			'50x30' => array( __( 'Label roll 50 × 30 mm', 'codereyes-mobile-shop' ), 50.0, 30.0 ),
			'a4'    => array( __( 'A4 sheet, 21 labels (63.5 × 38.1 mm)', 'codereyes-mobile-shop' ), 63.5, 38.1 ),
		);
	}

	/**
	 * A known size key, or the small roll.
	 *
	 * @param string $size Requested size.
	 * @return string
	 */
	public static function size( string $size ): string {
		return array_key_exists( $size, self::sizes() ) ? $size : '38x25';
	}

	/**
	 * The scannable code: IMEI for phones, otherwise the SKU when it is short
	 * enough to scan on a small label, otherwise CMS + item number.
	 *
	 * @param array $item Item row.
	 * @return string
	 */
	public static function code( array $item ): string {
		$imei = (string) ( $item['imei1'] ?? '' );

		if ( self::IMEI_LENGTH === strlen( $imei ) && ctype_digit( $imei ) ) {
			return $imei;
		}

		$sku = trim( (string) ( $item['sku'] ?? '' ) );

		if ( '' !== $sku && strlen( $sku ) <= Cmshop_Code128::MAX_LENGTH && 1 === preg_match( '/^[\x20-\x7E]+$/', $sku ) && strlen( Cmshop_Code128::modules( $sku ) ) <= self::MAX_MODULES ) {
			return $sku;
		}

		return self::CODE_PREFIX . str_pad( (string) (int) $item['id'], self::CODE_DIGITS, '0', STR_PAD_LEFT );
	}

	/**
	 * Item number from a scanned CMS code.
	 *
	 * @param string $code Scanned text.
	 * @return int|null
	 */
	public static function id_from_code( string $code ): ?int {
		return 1 === preg_match( '/^' . self::CODE_PREFIX . '(\d{1,10})$/i', trim( $code ), $match ) ? (int) $match[1] : null;
	}

	/**
	 * One sticker.
	 *
	 * @param array $item Item row.
	 * @return array{id: int, title: string, detail: string, condition: string, price: string, code: string}
	 */
	public static function label( array $item ): array {
		$is_phone = Cmshop_Item::TYPE_PHONE === ( $item['item_type'] ?? '' );
		$title    = $is_phone ? Cmshop_Model_Listing::title( $item ) : '';

		return array(
			'id'        => (int) $item['id'],
			'title'     => '' === $title ? Cmshop_Item::display_name( $item ) : $title,
			'detail'    => $is_phone ? implode( ' · ', array_filter( array( trim( (string) $item['storage'] ), trim( (string) $item['color'] ) ) ) ) : '',
			'condition' => $is_phone ? self::condition( $item ) : '',
			'price'     => (string) $item['sale_price'],
			'code'      => self::code( $item ),
		);
	}

	/**
	 * Stickers for a set of items, repeated per the copies choice.
	 *
	 * @param array[] $items  Item rows.
	 * @param string  $copies 1, 2, 3 or "stock" (one per unit in stock).
	 * @return array[]
	 */
	public static function sheet( array $items, string $copies ): array {
		$copies = in_array( $copies, self::COPIES, true ) ? $copies : '1';
		$labels = array();

		foreach ( $items as $item ) {
			$count  = 'stock' === $copies ? max( 1, (int) $item['quantity'] ) : (int) $copies;
			$labels = array_merge( $labels, array_fill( 0, min( $count, self::MAX_LABELS ), self::label( $item ) ) );

			if ( count( $labels ) >= self::MAX_LABELS ) {
				break;
			}
		}

		return array_slice( $labels, 0, self::MAX_LABELS );
	}

	/**
	 * "Good · Battery 91%", "New (sealed)".
	 *
	 * @param array $item Phone row.
	 * @return string
	 */
	private static function condition( array $item ): string {
		$parts   = array( Cmshop_Item::label( Cmshop_Item::grades(), (string) $item['grade'] ) );
		$battery = $item['battery_health'] ?? null;

		if ( null !== $battery && '' !== (string) $battery ) {
			/* translators: %d: battery health percentage. */
			$parts[] = sprintf( __( 'Battery %d%%', 'codereyes-mobile-shop' ), (int) $battery );
		}

		return implode( ' · ', array_filter( $parts ) );
	}
}
