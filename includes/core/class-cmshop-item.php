<?php
/**
 * Stock item vocabulary: types, statuses and condition grades.
 *
 * @package CodereyesMobileShop
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Phones are tracked one by one (quantity 1, by IMEI); accessories by quantity.
 */
final class Cmshop_Item {

	public const TYPE_PHONE     = 'phone';
	public const TYPE_ACCESSORY = 'accessory';

	public const STATUS_IN_STOCK = 'in_stock';
	public const STATUS_RESERVED = 'reserved';
	public const STATUS_SOLD     = 'sold';

	/**
	 * Item types and their labels.
	 *
	 * @return array<string,string>
	 */
	public static function types(): array {
		return array(
			self::TYPE_PHONE     => __( 'Phone', 'codereyes-mobile-shop' ),
			self::TYPE_ACCESSORY => __( 'Accessory', 'codereyes-mobile-shop' ),
		);
	}

	/**
	 * Statuses and their labels.
	 *
	 * @return array<string,string>
	 */
	public static function statuses(): array {
		return array(
			self::STATUS_IN_STOCK => __( 'In stock', 'codereyes-mobile-shop' ),
			self::STATUS_RESERVED => __( 'Reserved', 'codereyes-mobile-shop' ),
			self::STATUS_SOLD     => __( 'Sold', 'codereyes-mobile-shop' ),
		);
	}

	/**
	 * Condition grades and their labels.
	 *
	 * @return array<string,string>
	 */
	public static function grades(): array {
		return array(
			'new'      => __( 'New (sealed)', 'codereyes-mobile-shop' ),
			'like_new' => __( 'Like new', 'codereyes-mobile-shop' ),
			'good'     => __( 'Good', 'codereyes-mobile-shop' ),
			'fair'     => __( 'Fair', 'codereyes-mobile-shop' ),
		);
	}

	/**
	 * Display name of an item row: "Samsung Galaxy A15 128GB Black" or the accessory name.
	 *
	 * @param array $item Item row.
	 * @return string
	 */
	public static function display_name( array $item ): string {
		if ( self::TYPE_PHONE !== ( $item['item_type'] ?? '' ) ) {
			return (string) ( $item['name'] ?? '' );
		}

		$parts = array( $item['brand'] ?? '', $item['model'] ?? '', $item['storage'] ?? '', $item['color'] ?? '' );

		return trim( implode( ' ', array_filter( array_map( 'strval', $parts ) ) ) );
	}

	/**
	 * Status a row should have for a given quantity.
	 *
	 * A reserved phone stays reserved while it is still in stock.
	 *
	 * @param string $current  Current status.
	 * @param int    $quantity New quantity.
	 * @return string
	 */
	public static function status_for_quantity( string $current, int $quantity ): string {
		if ( $quantity <= 0 ) {
			return self::STATUS_SOLD;
		}

		return self::STATUS_RESERVED === $current ? self::STATUS_RESERVED : self::STATUS_IN_STOCK;
	}

	/**
	 * Label for a key in one of the vocabularies, or the key itself.
	 *
	 * @param array<string,string> $labels Vocabulary.
	 * @param string               $key    Key.
	 * @return string
	 */
	public static function label( array $labels, string $key ): string {
		return $labels[ $key ] ?? $key;
	}
}
